<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudentSelfAssessment;
use App\Models\StudentWorkLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * จัดการ/รายงานงานนักศึกษา — สำหรับ super_admin/admin
 * ดูบันทึกของนักศึกษาทุกคน + เจาะรายคน + ลบ + export Excel ให้ผู้บริหาร
 */
class StudentAdminController extends Controller
{
    /** GET /api/student-admin/students — รายชื่อนักศึกษา + สรุปผลงานรายคน */
    public function students(Request $request): JsonResponse
    {
        $students = User::query()
            ->whereHas('roles', fn ($r) => $r->where('name', 'student'))
            ->when($request->filled('amphur_id'), fn ($q) => $q->where('amphur_id', (int) $request->amphur_id))
            ->when($request->filled('work_unit_type'), fn ($q) => $q->where('work_unit_type', $request->work_unit_type))
            ->when($request->filled('q'), function ($q) use ($request) {
                $kw = '%' . $request->q . '%';
                $q->where(fn ($w) => $w->where('name', 'like', $kw)->orWhere('student_id', 'like', $kw));
            })
            ->with('amphur:id,name')
            ->orderBy('name')
            ->get();

        // สรุปผลรายคน (แยก query กัน join บวกซ้ำ)
        $result = DB::table('student_work_logs')
            ->groupBy('user_id')
            ->selectRaw('user_id, COUNT(*) as days, SUM(registered_success) as success, SUM(registered_fail) as fail, MAX(work_date) as last_date')
            ->get()->keyBy('user_id');

        $service = DB::table('student_work_log_entries as e')
            ->join('student_work_logs as l', 'l.id', '=', 'e.work_log_id')
            ->groupBy('l.user_id')
            ->selectRaw('l.user_id, SUM(e.service_count) as service')
            ->pluck('service', 'user_id');

        $assessed = StudentSelfAssessment::pluck('user_id')->flip();

        $data = $students->map(function ($u) use ($result, $service, $assessed) {
            $r = $result->get($u->id);
            return [
                'id'         => $u->id,
                'name'       => $u->name,
                'student_id' => $u->student_id,
                'faculty'    => $u->faculty,
                'major'      => $u->major,
                'phone'      => $u->phone,
                'unit'       => $u->work_unit_type === 'bank'
                    ? 'ธนาคาร ' . strtoupper($u->bank_sub_channel ?? '') . ' ' . ($u->bank_branch ?? '')
                    : ('อ.' . ($u->amphur?->name ?? '-')),
                'active'     => (bool) $u->active,
                'days'       => (int) ($r->days ?? 0),
                'service'    => (int) ($service[$u->id] ?? 0),
                'success'    => (int) ($r->success ?? 0),
                'fail'       => (int) ($r->fail ?? 0),
                'assessed'   => $assessed->has($u->id),
                'last_date'  => $r->last_date ?? null,
            ];
        })
        // เรียงตามกิจกรรมล่าสุดก่อน (คนที่ยังไม่มีบันทึกอยู่ท้ายสุด)
        ->sortByDesc(fn ($s) => $s['last_date'] ?? '')->values();

        return response()->json(['data' => $data]);
    }

    /** GET /api/student-admin/work-logs?user_id=&from=&to=&amphur_id= */
    public function workLogs(Request $request): JsonResponse
    {
        $logs = StudentWorkLog::query()
            ->with('user:id,name,student_id,faculty,amphur_id,work_unit_type,bank_sub_channel,bank_branch')
            ->with('files')
            ->withCount(['entries', 'cases', 'files'])
            ->withSum('entries as service_total', 'service_count')
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', (int) $request->user_id))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('work_date', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('work_date', '<=', $request->to))
            ->when($request->filled('amphur_id'), fn ($q) => $q->whereHas('user', fn ($u) => $u->where('amphur_id', (int) $request->amphur_id)))
            ->orderByDesc('work_date')->orderByDesc('id')
            ->limit(500)
            ->get();

        return response()->json(['data' => $logs]);
    }

    /** GET /api/student-admin/work-logs/{id} — รายละเอียดเต็ม (ของนักศึกษาคนใดก็ได้) */
    public function show(int $id): JsonResponse
    {
        $log = StudentWorkLog::with(['user:id,name,student_id,faculty,major,phone', 'entries', 'cases', 'files'])->findOrFail($id);
        return response()->json(['data' => $log]);
    }

    /**
     * GET /api/student-admin/photos — ภาพการปฏิบัติงานของนักศึกษา (พร้อมพิกัด+ผู้บันทึก)
     * ?random=1&limit=7 (สุ่มตัวอย่าง) | ?all=1 (ทั้งหมด) | filter: date|from|to|amphur_id|bank
     */
    public function photos(Request $request): JsonResponse
    {
        $q = DB::table('student_work_files as f')
            ->join('student_work_logs as l', 'l.id', '=', 'f.work_log_id')
            ->join('users as u', 'u.id', '=', 'l.user_id')
            ->leftJoin('amphurs as a', 'a.id', '=', 'u.amphur_id')
            ->where('f.category', 'photo')
            ->when($request->filled('date'), fn ($x) => $x->whereDate('l.work_date', $request->date))
            ->when($request->filled('from'), fn ($x) => $x->whereDate('l.work_date', '>=', $request->from))
            ->when($request->filled('to'), fn ($x) => $x->whereDate('l.work_date', '<=', $request->to))
            ->when($request->filled('amphur_id'), fn ($x) => $x->where('u.amphur_id', (int) $request->amphur_id))
            ->when($request->filled('bank'), fn ($x) => $x->where('u.work_unit_type', 'bank')->where('u.bank_sub_channel', $request->bank))
            ->select('f.id', 'f.original_name', 'f.lat as flat', 'f.lng as flng',
                'l.lat as llat', 'l.lng as llng', 'l.work_date',
                'u.name as student', 'u.faculty', 'u.work_unit_type', 'u.bank_sub_channel', 'u.bank_branch', 'a.name as amphur');

        if ($request->boolean('random')) {
            $q->inRandomOrder()->limit(min((int) $request->input('limit', 7), 50));
        } else {
            $q->orderByDesc('l.work_date')->orderByDesc('f.id')->limit($request->boolean('all') ? 500 : min((int) $request->input('limit', 7), 50));
        }

        $rows = $q->get()->map(fn ($r) => [
            'id'        => $r->id,
            'url'       => "/api/files/work/{$r->id}",
            'name'      => $r->original_name,
            'lat'       => $r->flat ?? $r->llat,
            'lng'       => $r->flng ?? $r->llng,
            'work_date' => $r->work_date,
            'student'   => $r->student,
            'faculty'   => $r->faculty,
            'unit'      => $r->work_unit_type === 'bank'
                ? 'ธนาคาร ' . strtoupper($r->bank_sub_channel ?? '') . ' ' . ($r->bank_branch ?? '')
                : ('อ.' . ($r->amphur ?? '-')),
        ]);

        return response()->json(['data' => $rows]);
    }

    /** DELETE /api/student-admin/work-logs/{id} — admin จัดการ (ลบบันทึกที่ไม่ถูกต้อง) */
    public function destroy(int $id): JsonResponse
    {
        StudentWorkLog::findOrFail($id)->delete();
        return response()->json(['message' => 'ลบบันทึกเรียบร้อย']);
    }

    /** GET /api/student-admin/export — Report Excel สำหรับผู้บริหาร */
    public function export(Request $request): BinaryFileResponse
    {
        $rows = StudentWorkLog::query()
            ->with('user:id,name,student_id,faculty,amphur_id,work_unit_type,bank_sub_channel,bank_branch')
            ->with('user.amphur:id,name')
            ->withCount(['entries', 'cases'])
            ->withSum('entries as service_total', 'service_count')
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', (int) $request->user_id))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('work_date', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('work_date', '<=', $request->to))
            ->when($request->filled('amphur_id'), fn ($q) => $q->whereHas('user', fn ($u) => $u->where('amphur_id', (int) $request->amphur_id)))
            ->orderBy('work_date')
            ->get();

        $headings = ['ลำดับ', 'วันที่', 'นักศึกษา', 'รหัส นศ.', 'คณะ', 'หน่วยปฏิบัติงาน', 'ผู้รับบริการ', 'ลงทะเบียนสำเร็จ', 'ไม่สำเร็จ', 'จำนวนกิจกรรม', 'กรณีปัญหา', 'ละติจูด', 'ลองติจูด', 'ผู้ควบคุมงาน'];
        $filename = 'รายงานการปฏิบัติงานนักศึกษา_' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(new class($rows, $headings) implements FromCollection, WithHeadings, WithMapping {
            public function __construct(private $rows, private $heads) {}
            public function collection() { return $this->rows; }
            public function headings(): array { return $this->heads; }
            public function map($l): array {
                static $i = 0; $i++;
                $u = $l->user;
                $unit = $u?->work_unit_type === 'bank'
                    ? 'ธนาคาร ' . strtoupper($u->bank_sub_channel ?? '') . ' ' . ($u->bank_branch ?? '')
                    : ('อ.' . ($u?->amphur?->name ?? '-'));
                return [
                    $i,
                    optional($l->work_date)->format('d/m/Y'),
                    $u?->name ?? '',
                    $u?->student_id ?? '',
                    $u?->faculty ?? '',
                    $unit,
                    (int) ($l->service_total ?? 0),
                    (int) $l->registered_success,
                    (int) $l->registered_fail,
                    (int) $l->entries_count,
                    (int) $l->cases_count,
                    $l->lat,
                    $l->lng,
                    $l->supervisor_name ?? '',
                ];
            }
        }, $filename);
    }

    /** GET /api/student-admin/files-zip?category=worklog_doc|reimburse_doc|photo — ดาวน์โหลดไฟล์ทั้งหมวดเป็น ZIP */
    public function filesZip(Request $request)
    {
        $category = $request->input('category');
        abort_unless(in_array($category, ['worklog_doc', 'reimburse_doc', 'photo'], true), 422, 'หมวดไฟล์ไม่ถูกต้อง');

        $files = DB::table('student_work_files as f')
            ->join('student_work_logs as l', 'l.id', '=', 'f.work_log_id')
            ->join('users as u', 'u.id', '=', 'l.user_id')
            ->leftJoin('amphurs as a', 'a.id', '=', 'u.amphur_id')
            ->where('f.category', $category)
            ->when($request->filled('from'), fn ($q) => $q->whereDate('l.work_date', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('l.work_date', '<=', $request->to))
            ->when($request->filled('amphur_id'), fn ($q) => $q->where('u.amphur_id', (int) $request->amphur_id))
            ->when($request->filled('bank'), fn ($q) => $q->where('u.work_unit_type', 'bank')->where('u.bank_sub_channel', $request->bank))
            ->select('f.path', 'f.disk', 'f.original_name', 'l.work_date', 'u.name as student', 'a.name as amphur', 'u.work_unit_type', 'u.bank_sub_channel')
            ->orderBy('l.work_date')->limit(2000)->get();

        abort_if($files->isEmpty(), 404, 'ไม่พบไฟล์ตามเงื่อนไข');
        abort_unless(class_exists(\ZipArchive::class), 500, 'เซิร์ฟเวอร์ไม่รองรับการสร้าง ZIP (ext-zip)');

        $catLabel = ['worklog_doc' => 'ใบปฏิบัติงาน', 'reimburse_doc' => 'เอกสารเบิกจ่าย', 'photo' => 'ภาพการปฏิบัติงาน'][$category];
        $dir = storage_path('app/tmp');
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $zipPath = $dir . '/' . $catLabel . '_' . now()->format('Ymd_His') . '.zip';

        $zip = new \ZipArchive();
        abort_unless($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true, 500, 'สร้างไฟล์ ZIP ไม่ได้');

        $seen = [];
        foreach ($files as $f) {
            $abs = \Storage::disk($f->disk ?: 'local')->path($f->path);
            if (!is_file($abs)) continue;
            $place = $f->work_unit_type === 'bank' ? ('ธนาคาร' . strtoupper($f->bank_sub_channel ?? '')) : ($f->amphur ?? 'อื่นๆ');
            $date = $f->work_date ? date('Ymd', strtotime($f->work_date)) : '0000';
            $base = $date . '_' . preg_replace('/[\\\\\/:*?"<>|]/', '-', $f->student . '_' . $place . '_' . $f->original_name);
            $name = $base; $i = 1;
            while (isset($seen[$name])) {
                $name = pathinfo($base, PATHINFO_FILENAME) . "($i)." . pathinfo($base, PATHINFO_EXTENSION); $i++;
            }
            $seen[$name] = true;
            $zip->addFile($abs, $catLabel . '/' . $name);
        }
        $zip->close();

        return response()->download($zipPath, basename($zipPath))->deleteFileAfterSend(true);
    }

    /** GET /api/student-admin/report?group_by=student|amphur|bank|overall — รายงานสรุปหลายมิติ (Excel) */
    public function report(Request $request): BinaryFileResponse
    {
        $groupBy = in_array($request->input('group_by'), ['student', 'amphur', 'bank', 'overall'], true)
            ? $request->input('group_by') : 'student';

        $logs = DB::table('student_work_logs as l')
            ->join('users as u', 'u.id', '=', 'l.user_id')
            ->leftJoin('amphurs as a', 'a.id', '=', 'u.amphur_id')
            ->when($request->filled('from'), fn ($q) => $q->whereDate('l.work_date', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('l.work_date', '<=', $request->to))
            ->when($request->filled('amphur_id'), fn ($q) => $q->where('u.amphur_id', (int) $request->amphur_id))
            ->select('l.id', 'l.user_id', 'l.registered_success', 'l.registered_fail', 'l.lat', 'l.lng', 'l.work_date',
                'u.name as student', 'u.faculty', 'u.work_unit_type', 'u.bank_sub_channel', 'u.bank_branch', 'a.name as amphur')
            ->get();

        $service = DB::table('student_work_log_entries')->selectRaw('work_log_id, SUM(service_count) as s')->groupBy('work_log_id')->pluck('s', 'work_log_id');
        $files   = DB::table('student_work_files')->selectRaw('work_log_id, COUNT(*) as c')->groupBy('work_log_id')->pluck('c', 'work_log_id');

        $groups = [];
        foreach ($logs as $l) {
            [$key, $label] = match ($groupBy) {
                'overall' => ['all', 'ภาพรวมทั้งหมด'],
                'amphur'  => [$l->amphur ?? '?', $l->amphur ?? '— ไม่ระบุอำเภอ —'],
                'bank'    => $l->work_unit_type === 'bank'
                    ? ['b_' . $l->bank_sub_channel, 'ธนาคาร ' . strtoupper($l->bank_sub_channel ?? '-')]
                    : ['nonbank', '— ไม่ใช่ธนาคาร —'],
                default   => [$l->user_id, $l->student],
            };
            $unit = $l->work_unit_type === 'bank'
                ? 'ธนาคาร ' . strtoupper($l->bank_sub_channel ?? '') . ' ' . ($l->bank_branch ?? '')
                : ('อ.' . ($l->amphur ?? '-'));
            if (!isset($groups[$key])) {
                $groups[$key] = ['label' => $label, 'users' => [], 'days' => 0, 'service' => 0, 'success' => 0, 'fail' => 0, 'files' => 0,
                    'faculty' => $l->faculty, 'unit' => $unit, 'latest' => null, 'lat' => null, 'lng' => null];
            }
            $groups[$key]['users'][$l->user_id] = true;
            $groups[$key]['days']++;
            $groups[$key]['service'] += (int) ($service[$l->id] ?? 0);
            $groups[$key]['success'] += (int) $l->registered_success;
            $groups[$key]['fail']    += (int) $l->registered_fail;
            $groups[$key]['files']   += (int) ($files[$l->id] ?? 0);
            // เก็บพิกัดจากบันทึกล่าสุด (สำหรับรายงานรายคน)
            if ($groups[$key]['latest'] === null || $l->work_date >= $groups[$key]['latest']) {
                $groups[$key]['latest'] = $l->work_date;
                $groups[$key]['lat'] = $l->lat;
                $groups[$key]['lng'] = $l->lng;
            }
        }

        $rows = collect($groups)->map(fn ($g) => [
            'label' => $g['label'], 'students' => count($g['users']), 'days' => $g['days'],
            'service' => $g['service'], 'success' => $g['success'], 'fail' => $g['fail'], 'files' => $g['files'],
            'faculty' => $g['faculty'], 'unit' => $g['unit'], 'lat' => $g['lat'], 'lng' => $g['lng'],
        ])->sortByDesc('service')->values();

        $title = ['student' => 'รายคน', 'amphur' => 'รายอำเภอ', 'bank' => 'รายธนาคาร', 'overall' => 'ภาพรวม'][$groupBy];

        if ($groupBy === 'student') {
            // รายคน: คอลัมน์เฉพาะบุคคล + ละติจูด/ลองติจูด (จากบันทึกล่าสุด)
            $headings = ['ลำดับ', 'นักศึกษา', 'คณะ', 'หน่วยปฏิบัติงาน', 'วัน-ครั้ง', 'ผู้รับบริการ', 'ลงทะเบียนสำเร็จ', 'ไม่สำเร็จ', 'ไฟล์แนบ', 'ละติจูด', 'ลองติจูด'];
            $mapper = fn ($i, $r) => [$i, $r['label'], $r['faculty'], $r['unit'], $r['days'], $r['service'], $r['success'], $r['fail'], $r['files'], $r['lat'], $r['lng']];
        } else {
            $col0 = ['amphur' => 'อำเภอ', 'bank' => 'ธนาคาร', 'overall' => 'ขอบเขต'][$groupBy];
            $headings = ['ลำดับ', $col0, 'จำนวนนักศึกษา', 'วัน-ครั้ง', 'ผู้รับบริการ', 'ลงทะเบียนสำเร็จ', 'ไม่สำเร็จ', 'ไฟล์แนบ'];
            $mapper = fn ($i, $r) => [$i, $r['label'], $r['students'], $r['days'], $r['service'], $r['success'], $r['fail'], $r['files']];
        }
        $filename = 'รายงานนักศึกษา_' . $title . '_' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(new class($rows, $headings, $mapper) implements FromCollection, WithHeadings, WithMapping {
            public function __construct(private $rows, private $heads, private $mapper) {}
            public function collection() { return $this->rows; }
            public function headings(): array { return $this->heads; }
            public function map($r): array {
                static $i = 0; $i++;
                return ($this->mapper)($i, $r);
            }
        }, $filename);
    }
}

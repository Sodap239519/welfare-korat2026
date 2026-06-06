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
            ->selectRaw('user_id, COUNT(*) as days, SUM(registered_success) as success, SUM(registered_fail) as fail')
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
            ];
        });

        return response()->json(['data' => $data]);
    }

    /** GET /api/student-admin/work-logs?user_id=&from=&to=&amphur_id= */
    public function workLogs(Request $request): JsonResponse
    {
        $logs = StudentWorkLog::query()
            ->with('user:id,name,student_id,faculty,amphur_id,work_unit_type,bank_sub_channel,bank_branch')
            ->withCount(['entries', 'cases'])
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
        $log = StudentWorkLog::with(['user:id,name,student_id,faculty,major,phone', 'entries', 'cases'])->findOrFail($id);
        return response()->json(['data' => $log]);
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

        $headings = ['ลำดับ', 'วันที่', 'นักศึกษา', 'รหัส นศ.', 'คณะ', 'หน่วยปฏิบัติงาน', 'ผู้รับบริการ', 'ลงทะเบียนสำเร็จ', 'ไม่สำเร็จ', 'จำนวนกิจกรรม', 'กรณีปัญหา', 'ผู้ควบคุมงาน'];
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
                    $l->supervisor_name ?? '',
                ];
            }
        }, $filename);
    }
}

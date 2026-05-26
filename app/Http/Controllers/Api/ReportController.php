<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    private const LEVELS = ['amphur', 'tambon', 'village'];

    /**
     * GET /api/reports/daily-villages
     *   ?level=amphur|tambon|village (default village)
     *   ?date= (display only — current snapshot)
     *   ?amphur_id=&tambon_id=
     *   ?page=1&per_page=35
     */
    public function dailyVillages(Request $request): JsonResponse
    {
        $level   = $this->validatedLevel($request);
        $perPage = max(1, min((int) $request->input('per_page', 35), 200));

        $all = $this->aggregateQuery($request, $level)->get();
        $mapped = $all->map(fn ($r) => $this->mapRow($r, $level));

        // summary row — รวมทุกแถว
        $summary = [
            'total'     => (int) $mapped->sum('total'),
            'untracked' => (int) $mapped->sum('untracked'),
            's_41' => (int) $mapped->sum('s_41'),
            's_42' => (int) $mapped->sum('s_42'),
            's_43' => (int) $mapped->sum('s_43'),
            's_44' => (int) $mapped->sum('s_44'),
            's_45' => (int) $mapped->sum('s_45'),
            's_46' => (int) $mapped->sum('s_46'),
            's_47' => (int) $mapped->sum('s_47'),
            'done'  => (int) $mapped->sum('done'),
        ];
        $summary['pct'] = $summary['total'] > 0 ? round(($summary['done'] / $summary['total']) * 100, 1) : 0;

        // paginate (frontend-style: simple offset)
        $page  = max(1, (int) $request->input('page', 1));
        $total = $mapped->count();
        $rows  = $mapped->slice(($page - 1) * $perPage, $perPage)->values();

        return response()->json([
            'level'        => $level,
            'data'         => $rows,
            'summary'      => $summary,
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => max(1, (int) ceil($total / $perPage)),
        ]);
    }

    /** GET /api/reports/daily-villages/export — สรุปยอดทุกสถานะ */
    public function exportDailyVillages(Request $request): BinaryFileResponse
    {
        $level = $this->validatedLevel($request);
        $rows = $this->aggregateQuery($request, $level)->get()
            ->map(fn ($r) => $this->mapRow($r, $level));

        $labelName = $level === 'amphur' ? 'อำเภอ' : ($level === 'tambon' ? 'ตำบล' : 'หมู่บ้าน');
        $headings = [
            $labelName, 'อำเภอ', 'ตำบล', 'เป้า',
            'ยังไม่ถูกติดตาม', '4.1 ไม่ประสงค์', '4.2 ลงทะเบียน', '4.3 เตรียมเอกสาร',
            '4.4 ส่งเอกสารเพิ่ม', '4.5 รออุทธรณ์', '4.6 รอยืนยัน', '4.7 ใช้สิทธิ',
            'รวม', '%', 'สถานะ'
        ];
        $filename = "สรุปยอดทุกสถานะ_{$level}_".now()->format('Y-m-d').'.xlsx';

        return Excel::download(new class($rows, $headings) implements FromCollection, WithHeadings, WithMapping {
            public function __construct(private $rows, private $heads) {}
            public function collection() { return $this->rows; }
            public function headings(): array { return $this->heads; }
            public function map($r): array {
                return [
                    $r['name'],
                    $r['amphur'] ?? '—',
                    $r['tambon'] ?? '—',
                    $r['total'],
                    $r['untracked'] ?? 0,
                    $r['s_41'] ?? 0, $r['s_42'] ?? 0, $r['s_43'] ?? 0,
                    $r['s_44'] ?? 0, $r['s_45'] ?? 0, $r['s_46'] ?? 0, $r['s_47'] ?? 0,
                    $r['done'], $r['pct'].'%', $r['level'],
                ];
            }
        }, $filename);
    }

    /** GET /api/reports/export/targets-raw — รายชื่อเป้าหมายต้นฉบับ */
    public function exportTargetsRaw(Request $request): BinaryFileResponse
    {
        $rows = \App\Models\Target::query()
            ->with(['village.tambon.amphur', 'household'])
            ->where('active', true)
            ->when($request->filled('amphur_id'),  fn ($x) => $x->where('amphur_id',  (int) $request->amphur_id))
            ->when($request->filled('tambon_id'),  fn ($x) => $x->where('tambon_id',  (int) $request->tambon_id))
            ->when($request->filled('village_id'), fn ($x) => $x->where('village_id', (int) $request->village_id))
            ->orderBy('amphur_id')->orderBy('tambon_id')->orderBy('village_id')->orderBy('id')
            ->get();

        $headings = ['ลำดับ', 'คำนำหน้า', 'ชื่อ', 'นามสกุล', 'เลขประจำตัวประชาชน',
                     'บ้านเลขที่', 'รหัสบ้าน', 'หมู่ที่', 'หมู่บ้าน', 'ตำบล', 'อำเภอ',
                     'รายได้/ปี', 'ปี (พ.ศ.)', 'เคยได้รับบัตร'];
        $filename = "รายชื่อเป้าหมายต้นฉบับ_".now()->format('Y-m-d').'.xlsx';

        return Excel::download(new class($rows, $headings) implements FromCollection, WithHeadings, WithMapping {
            public function __construct(private $rows, private $heads) {}
            public function collection() { return $this->rows; }
            public function headings(): array { return $this->heads; }
            public function map($t): array {
                static $i = 0; $i++;
                return [
                    $i,
                    $t->prefix ?? '',
                    $t->first_name,
                    $t->last_name,
                    $t->id_card ?? '',
                    $t->address_no ?? '',
                    $t->household?->house_code ?? '',
                    $t->village?->moo ?? '',
                    $t->village?->name ?? '',
                    $t->village?->tambon?->name ?? '',
                    $t->village?->tambon?->amphur?->name ?? '',
                    $t->annual_income ?? 0,
                    $t->year ?? '',
                    $t->has_old_welfare ? 'เคย' : '—',
                ];
            }
        }, $filename);
    }

    /** GET /api/reports/export/targets-status — รายชื่อเป้าหมายพร้อมสถานะปัจจุบัน */
    public function exportTargetsStatus(Request $request): BinaryFileResponse
    {
        $q = \App\Models\Target::query()
            ->with(['village.tambon.amphur', 'currentStatus.channel'])
            ->where('active', true)
            ->when($request->filled('amphur_id'),  fn ($x) => $x->where('amphur_id',  (int) $request->amphur_id))
            ->when($request->filled('tambon_id'),  fn ($x) => $x->where('tambon_id',  (int) $request->tambon_id))
            ->when($request->filled('village_id'), fn ($x) => $x->where('village_id', (int) $request->village_id))
            ->orderBy('amphur_id')->orderBy('tambon_id')->orderBy('village_id')->orderBy('id');

        $rows = $q->get();
        $headings = ['ลำดับ', 'ชื่อ-สกุล', 'หมู่บ้าน', 'หมู่ที่', 'ตำบล', 'อำเภอ',
                     'สถานะปัจจุบัน', 'ช่องทาง', 'อัปเดตล่าสุด', 'หมายเหตุ'];
        $filename = "รายชื่อเป้าหมาย+สถานะ_".now()->format('Y-m-d').'.xlsx';

        return Excel::download(new class($rows, $headings) implements FromCollection, WithHeadings, WithMapping {
            public function __construct(private $rows, private $heads) {}
            public function collection() { return $this->rows; }
            public function headings(): array { return $this->heads; }
            public function map($t): array {
                static $i = 0; $i++;
                $statusMap = [
                    '4.1' => 'ไม่ประสงค์', '4.2' => 'ลงทะเบียน', '4.3' => 'เตรียมเอกสาร',
                    '4.4' => 'ส่งเอกสารเพิ่ม', '4.5' => 'รออุทธรณ์', '4.6' => 'รอยืนยันตัวตน', '4.7' => 'ใช้สิทธิแล้ว',
                ];
                $cs = $t->currentStatus;
                return [
                    $i,
                    trim(($t->prefix ?? '').' '.$t->first_name.' '.$t->last_name),
                    $t->village?->name ?? '',
                    $t->village?->moo ?? '',
                    $t->village?->tambon?->name ?? '',
                    $t->village?->tambon?->amphur?->name ?? '',
                    $cs ? ($cs->status_code.' '.($statusMap[$cs->status_code] ?? '')) : 'ยังไม่ถูกติดตาม',
                    $cs?->channel?->name ?? '',
                    $cs?->updated_at?->format('d/m/Y H:i') ?? '',
                    $cs?->note ?? '',
                ];
            }
        }, $filename);
    }

    /** GET /api/reports/export/trackers — รายชื่อผู้กำกับติดตาม */
    public function exportTrackers(Request $request): BinaryFileResponse
    {
        $rows = \App\Models\Tracker::with('village.tambon.amphur', 'user')
            ->where('active', true)
            ->when($request->filled('amphur_id'),
                fn ($q) => $q->whereHas('village.tambon', fn ($x) => $x->where('amphur_id', (int) $request->amphur_id)))
            ->orderBy('full_name')
            ->get();

        $headings = ['ลำดับ', 'ชื่อ-สกุล', 'ตำแหน่ง', 'เบอร์โทร', 'หมู่บ้าน', 'หมู่ที่', 'ตำบล', 'อำเภอ', 'มีบัญชี Login'];
        $filename = "รายชื่อผู้กำกับติดตาม_".now()->format('Y-m-d').'.xlsx';

        return Excel::download(new class($rows, $headings) implements FromCollection, WithHeadings, WithMapping {
            public function __construct(private $rows, private $heads) {}
            public function collection() { return $this->rows; }
            public function headings(): array { return $this->heads; }
            public function map($t): array {
                static $i = 0; $i++;
                return [
                    $i,
                    $t->full_name,
                    $t->position.($t->position_other ? ' ('.$t->position_other.')' : ''),
                    $t->phone ?? '',
                    $t->village?->name ?? '',
                    $t->village?->moo ?? '',
                    $t->village?->tambon?->name ?? '',
                    $t->village?->tambon?->amphur?->name ?? '',
                    $t->user_id ? '✓ มี' : '— ไม่มี',
                ];
            }
        }, $filename);
    }

    /** GET /api/reports/bottleneck */
    public function bottleneck(Request $request): JsonResponse
    {
        $offset = (int) $request->input('week_offset', 0);
        $weekStart = now()->startOfWeek()->addWeeks($offset);
        $weekEnd   = $weekStart->copy()->endOfWeek();

        $stuckLogs = DB::table('target_current_status')
            ->whereIn('status_code', ['4.4', '4.5'])
            ->selectRaw('status_code, COUNT(*) as cnt, AVG(TIMESTAMPDIFF(DAY, updated_at, NOW())) as avg_days')
            ->groupBy('status_code')
            ->get();

        $amphurProgress = DB::table('targets')
            ->leftJoin('target_current_status as tcs', 'tcs.target_id', '=', 'targets.id')
            ->join('amphurs', 'amphurs.id', '=', 'targets.amphur_id')
            ->where('targets.active', true)
            ->selectRaw('amphurs.id, amphurs.name,
                COUNT(*) as total,
                COUNT(CASE WHEN tcs.status_code IS NOT NULL AND tcs.status_code <> "4.1" THEN 1 END) as done')
            ->groupBy('amphurs.id', 'amphurs.name')
            ->havingRaw('done / GREATEST(total, 1) < 0.75')
            ->orderByRaw('COUNT(CASE WHEN tcs.status_code IS NOT NULL AND tcs.status_code <> "4.1" THEN 1 END) / GREATEST(COUNT(*), 1) ASC')
            ->limit(10)
            ->get();

        $inactiveTrackers = DB::table('trackers')
            ->join('villages', 'villages.id', '=', 'trackers.village_id')
            ->leftJoin('targets', 'targets.village_id', '=', 'villages.id')
            ->leftJoin('target_status_logs as logs', function ($j) {
                $j->on('logs.target_id', '=', 'targets.id')
                  ->where('logs.changed_at', '>=', now()->subDays(3));
            })
            ->where('trackers.active', true)
            ->selectRaw('trackers.id, trackers.full_name, trackers.position, villages.name as village,
                COUNT(logs.id) as recent_logs')
            ->groupBy('trackers.id', 'trackers.full_name', 'trackers.position', 'villages.name')
            ->having('recent_logs', '=', 0)
            ->limit(15)
            ->get();

        return response()->json([
            'week' => [
                'start' => $weekStart->toDateString(),
                'end'   => $weekEnd->toDateString(),
                'week_num' => (int) $weekStart->format('W'),
            ],
            'stuck_stages' => $stuckLogs->map(fn ($r) => [
                'status_code' => $r->status_code,
                'count'       => (int) $r->cnt,
                'avg_days'    => round((float) $r->avg_days, 1),
            ]),
            'lagging_amphurs' => $amphurProgress->map(fn ($r) => [
                'id'    => (int) $r->id,
                'name'  => $r->name,
                'total' => (int) $r->total,
                'done'  => (int) $r->done,
                'pct'   => $r->total > 0 ? round(($r->done / $r->total) * 100, 1) : 0,
            ]),
            'inactive_trackers' => $inactiveTrackers->map(fn ($r) => [
                'id'       => (int) $r->id,
                'name'     => $r->full_name,
                'position' => $r->position,
                'village'  => $r->village,
            ]),
        ]);
    }

    private function validatedLevel(Request $request): string
    {
        $level = (string) $request->input('level', 'village');
        return in_array($level, self::LEVELS, true) ? $level : 'village';
    }

    /**
     * Generic aggregation query — group by amphur / tambon / village
     */
    private function aggregateQuery(Request $request, string $level)
    {
        $base = DB::table('amphurs')
            ->join('tambons', 'tambons.amphur_id', '=', 'amphurs.id')
            ->join('villages', 'villages.tambon_id', '=', 'tambons.id')
            ->leftJoin('targets', function ($j) {
                $j->on('targets.village_id', '=', 'villages.id')->where('targets.active', '=', true);
            })
            ->leftJoin('target_current_status as tcs', 'tcs.target_id', '=', 'targets.id')
            ->when($request->filled('amphur_id'), fn ($q) => $q->where('amphurs.id', (int) $request->amphur_id))
            ->when($request->filled('tambon_id'), fn ($q) => $q->where('tambons.id', (int) $request->tambon_id));

        $metrics = '
            COUNT(DISTINCT targets.id) as total,
            COUNT(DISTINCT CASE WHEN tcs.status_code IS NULL THEN targets.id END) as untracked,
            COUNT(DISTINCT CASE WHEN tcs.status_code = "4.1" THEN targets.id END) as s_41,
            COUNT(DISTINCT CASE WHEN tcs.status_code = "4.2" THEN targets.id END) as s_42,
            COUNT(DISTINCT CASE WHEN tcs.status_code = "4.3" THEN targets.id END) as s_43,
            COUNT(DISTINCT CASE WHEN tcs.status_code = "4.4" THEN targets.id END) as s_44,
            COUNT(DISTINCT CASE WHEN tcs.status_code = "4.5" THEN targets.id END) as s_45,
            COUNT(DISTINCT CASE WHEN tcs.status_code = "4.6" THEN targets.id END) as s_46,
            COUNT(DISTINCT CASE WHEN tcs.status_code = "4.7" THEN targets.id END) as s_47,
            COUNT(DISTINCT CASE WHEN tcs.status_code IS NOT NULL AND tcs.status_code <> "4.1" THEN targets.id END) as done
        ';
        $orderBy = 'COUNT(DISTINCT CASE WHEN tcs.status_code IS NOT NULL AND tcs.status_code <> "4.1" THEN targets.id END) / GREATEST(COUNT(DISTINCT targets.id), 1) DESC';

        if ($level === 'amphur') {
            return $base->selectRaw("amphurs.id, amphurs.name as name, $metrics")
                        ->groupBy('amphurs.id', 'amphurs.name')
                        ->orderByRaw($orderBy);
        }
        if ($level === 'tambon') {
            return $base->selectRaw("tambons.id, tambons.name as name, amphurs.name as amphur, $metrics")
                        ->groupBy('tambons.id', 'tambons.name', 'amphurs.name')
                        ->orderByRaw($orderBy);
        }
        // village
        return $base->selectRaw("villages.id, villages.name as name, villages.moo,
                                 tambons.name as tambon, amphurs.name as amphur, $metrics")
                    ->groupBy('villages.id', 'villages.name', 'villages.moo', 'tambons.name', 'amphurs.name')
                    ->orderByRaw($orderBy);
    }

    private function mapRow($r, string $level): array
    {
        $pct = $r->total > 0 ? round(($r->done / $r->total) * 100) : 0;
        $row = [
            'id'          => (int) $r->id,
            'name'        => $r->name . (isset($r->moo) && $r->moo ? ' (ม.'.$r->moo.')' : ''),
            'total'       => (int) $r->total,
            'untracked'   => (int) $r->untracked,
            's_41'        => (int) $r->s_41,
            's_42'        => (int) $r->s_42,
            's_43'        => (int) $r->s_43,
            's_44'        => (int) $r->s_44,
            's_45'        => (int) $r->s_45,
            's_46'        => (int) $r->s_46,
            's_47'        => (int) $r->s_47,
            // เก็บ alias เดิมไว้ backward compat
            'kyc_done'    => (int) $r->s_47,
            'kyc_waiting' => (int) $r->s_46,
            'done'        => (int) $r->done,
            'pct'         => $pct,
            'level'       => $pct >= 80 ? 'ดีเยี่ยม' : ($pct >= 50 ? 'ปานกลาง' : 'ต้องเร่งรัด'),
        ];
        if ($level === 'village') {
            $row['tambon'] = $r->tambon;
            $row['amphur'] = $r->amphur;
        } elseif ($level === 'tambon') {
            $row['amphur'] = $r->amphur;
        }
        return $row;
    }
}

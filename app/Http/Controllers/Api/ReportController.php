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
     */
    public function dailyVillages(Request $request): JsonResponse
    {
        $level = $this->validatedLevel($request);
        $rows = $this->aggregateQuery($request, $level)->get();
        return response()->json([
            'level' => $level,
            'data'  => $rows->map(fn ($r) => $this->mapRow($r, $level)),
        ]);
    }

    /** GET /api/reports/daily-villages/export */
    public function exportDailyVillages(Request $request): BinaryFileResponse
    {
        $level = $this->validatedLevel($request);
        $rows = $this->aggregateQuery($request, $level)->get()
            ->map(fn ($r) => $this->mapRow($r, $level));

        $labelName = $level === 'amphur' ? 'อำเภอ' : ($level === 'tambon' ? 'ตำบล' : 'หมู่บ้าน');
        $headings = [$labelName, 'อำเภอ', 'ตำบล', 'เป้า', 'ยังไม่ถูกติดตาม', '4.7 ใช้สิทธิ', '4.6 รอยืนยัน', 'รวม', '%', 'สถานะ'];
        $filename = "รายงานสรุปยอด_{$level}_".now()->format('Y-m-d').'.xlsx';

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
                    $r['kyc_done'], $r['kyc_waiting'],
                    $r['done'], $r['pct'].'%', $r['level'],
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
            COUNT(DISTINCT CASE WHEN tcs.status_code = "4.7" THEN targets.id END) as kyc_done,
            COUNT(DISTINCT CASE WHEN tcs.status_code = "4.6" THEN targets.id END) as kyc_waiting,
            COUNT(DISTINCT CASE WHEN tcs.status_code IS NULL THEN targets.id END) as untracked,
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
            'kyc_done'    => (int) $r->kyc_done,
            'kyc_waiting' => (int) $r->kyc_waiting,
            'untracked'   => (int) $r->untracked,
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

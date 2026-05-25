<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TargetStatusLog;
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
    /** GET /api/reports/daily-villages?date=YYYY-MM-DD&amphur_id=&tambon_id= */
    public function dailyVillages(Request $request): JsonResponse
    {
        $rows = $this->dailyVillagesQuery($request)->get();
        return response()->json(['data' => $rows->map(fn ($r) => $this->mapVillageRow($r))]);
    }

    /** GET /api/reports/bottleneck?week_offset=0 (0 = สัปดาห์นี้, -1 = สัปดาห์ก่อน) */
    public function bottleneck(Request $request): JsonResponse
    {
        $offset = (int) $request->input('week_offset', 0);
        $weekStart = now()->startOfWeek()->addWeeks($offset);
        $weekEnd   = $weekStart->copy()->endOfWeek();

        // 1) ค้างที่ขั้น 4.4 / 4.5 — เฉลี่ยกี่วันค้าง
        $stuckLogs = DB::table('target_current_status')
            ->whereIn('status_code', ['4.4', '4.5'])
            ->selectRaw('
                status_code,
                COUNT(*) as cnt,
                AVG(TIMESTAMPDIFF(DAY, updated_at, NOW())) as avg_days
            ')
            ->groupBy('status_code')
            ->get();

        // 2) อำเภอที่ลงทะเบียนต่ำกว่าเป้า
        $amphurProgress = DB::table('targets')
            ->leftJoin('target_current_status as tcs', 'tcs.target_id', '=', 'targets.id')
            ->join('amphurs', 'amphurs.id', '=', 'targets.amphur_id')
            ->where('targets.active', true)
            ->selectRaw('
                amphurs.id, amphurs.name,
                COUNT(*) as total,
                COUNT(CASE WHEN tcs.status_code IS NOT NULL AND tcs.status_code <> "4.1" THEN 1 END) as done
            ')
            ->groupBy('amphurs.id', 'amphurs.name')
            ->havingRaw('done / GREATEST(total, 1) < 0.75')
            ->orderByRaw('done / GREATEST(total, 1) ASC')
            ->limit(10)
            ->get();

        // 3) ผู้ติดตามที่ไม่อัปเดตเกิน 3 วัน
        $inactiveTrackers = DB::table('trackers')
            ->join('villages', 'villages.id', '=', 'trackers.village_id')
            ->leftJoin('targets', 'targets.village_id', '=', 'villages.id')
            ->leftJoin('target_status_logs as logs', function ($j) {
                $j->on('logs.target_id', '=', 'targets.id')
                  ->where('logs.changed_at', '>=', now()->subDays(3));
            })
            ->where('trackers.active', true)
            ->selectRaw('
                trackers.id, trackers.full_name, trackers.position, villages.name as village,
                COUNT(logs.id) as recent_logs
            ')
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

    /** GET /api/reports/daily-villages/export?date=&amphur_id=&format=xlsx|csv */
    public function exportDailyVillages(Request $request): BinaryFileResponse
    {
        $rows = $this->dailyVillagesQuery($request)->get()->map(fn ($r) => $this->mapVillageRow($r));
        $format = $request->input('format', 'xlsx');
        $filename = 'รายงานรายหมู่บ้าน_'.now()->format('Y-m-d').'.'.$format;

        return Excel::download(new class($rows) implements FromCollection, WithHeadings, WithMapping {
            public function __construct(private $rows) {}
            public function collection() { return $this->rows; }
            public function headings(): array { return ['หมู่บ้าน', 'ตำบล', 'อำเภอ', 'เป้า', '4.7', '4.6', 'รวมลงทะเบียน', '%']; }
            public function map($r): array {
                return [$r['village'], $r['tambon'], $r['amphur'], $r['total'], $r['kyc_done'], $r['kyc_waiting'], $r['done'], $r['pct'].'%'];
            }
        }, $filename);
    }

    private function dailyVillagesQuery(Request $request)
    {
        return DB::table('villages')
            ->join('tambons', 'tambons.id', '=', 'villages.tambon_id')
            ->join('amphurs', 'amphurs.id', '=', 'tambons.amphur_id')
            ->leftJoin('targets', function ($j) {
                $j->on('targets.village_id', '=', 'villages.id')->where('targets.active', '=', true);
            })
            ->leftJoin('target_current_status as tcs', 'tcs.target_id', '=', 'targets.id')
            ->selectRaw('
                villages.id, villages.name as village, villages.moo,
                tambons.name as tambon, amphurs.name as amphur,
                COUNT(DISTINCT targets.id) as total,
                COUNT(DISTINCT CASE WHEN tcs.status_code = "4.7" THEN targets.id END) as kyc_done,
                COUNT(DISTINCT CASE WHEN tcs.status_code = "4.6" THEN targets.id END) as kyc_waiting,
                COUNT(DISTINCT CASE WHEN tcs.status_code IS NOT NULL AND tcs.status_code <> "4.1" THEN targets.id END) as done
            ')
            ->when($request->filled('amphur_id'), fn ($q) => $q->where('amphurs.id', (int) $request->amphur_id))
            ->when($request->filled('tambon_id'), fn ($q) => $q->where('tambons.id', (int) $request->tambon_id))
            ->groupBy('villages.id', 'villages.name', 'villages.moo', 'tambons.name', 'amphurs.name')
            ->orderByRaw('COUNT(DISTINCT CASE WHEN tcs.status_code IS NOT NULL AND tcs.status_code <> "4.1" THEN targets.id END) / GREATEST(COUNT(DISTINCT targets.id), 1) DESC');
    }

    private function mapVillageRow($r): array
    {
        $pct = $r->total > 0 ? round(($r->done / $r->total) * 100) : 0;
        return [
            'village_id'  => (int) $r->id,
            'village'     => $r->village . ($r->moo ? ' (ม.'.$r->moo.')' : ''),
            'tambon'      => $r->tambon,
            'amphur'      => $r->amphur,
            'total'       => (int) $r->total,
            'kyc_done'    => (int) $r->kyc_done,
            'kyc_waiting' => (int) $r->kyc_waiting,
            'done'        => (int) $r->done,
            'pct'         => $pct,
            'level'       => $pct >= 80 ? 'ดีเยี่ยม' : ($pct >= 50 ? 'ปานกลาง' : 'ต้องเร่งรัด'),
        ];
    }
}

<?php

namespace App\Console\Commands;

use App\Models\ReportSnapshot;
use App\Models\Target;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Weekly bottleneck analysis — รันทุกวันจันทร์ 06:00 (สัปดาห์ใหม่)
 *
 * Schedule:
 *   Schedule::command('reports:weekly-bottleneck')->weeklyOn(1, '06:00');
 */
class WeeklyBottleneck extends Command
{
    protected $signature = 'reports:weekly-bottleneck';
    protected $description = 'วิเคราะห์ Bottleneck รายสัปดาห์ + บันทึก snapshot';

    public function handle(): int
    {
        $weekStart = now()->subWeek()->startOfWeek();
        $weekEnd   = $weekStart->copy()->endOfWeek();
        $this->info("Analyzing bottleneck for week {$weekStart->toDateString()} → {$weekEnd->toDateString()}");

        // 1) ค้างที่ขั้น 4.4 / 4.5
        $stuck = DB::table('target_current_status')
            ->whereIn('status_code', ['4.4', '4.5'])
            ->selectRaw('status_code, COUNT(*) as cnt, AVG(TIMESTAMPDIFF(DAY, updated_at, NOW())) as avg_days')
            ->groupBy('status_code')
            ->get()
            ->map(fn ($r) => ['status_code' => $r->status_code, 'count' => (int) $r->cnt, 'avg_days' => round($r->avg_days, 1)]);

        // 2) อำเภอลงทะเบียนต่ำกว่า 75% ของเป้า
        $lagging = DB::table('targets')
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
            ->get()
            ->map(fn ($r) => [
                'id' => (int) $r->id, 'name' => $r->name,
                'total' => (int) $r->total, 'done' => (int) $r->done,
                'pct' => $r->total > 0 ? round(($r->done / $r->total) * 100, 1) : 0,
            ]);

        // 3) Tracker ที่ไม่อัปเดต > 3 วัน — group ตามคน (user_id) แล้วรวมพื้นที่ดูแล
        $trackerRows = DB::table('trackers')
            ->join('villages', 'villages.id', '=', 'trackers.village_id')
            ->leftJoin('targets', 'targets.village_id', '=', 'villages.id')
            ->leftJoin('target_status_logs as logs', function ($j) {
                $j->on('logs.target_id', '=', 'targets.id')
                  ->where('logs.changed_at', '>=', now()->subDays(3));
            })
            ->where('trackers.active', true)
            ->selectRaw('trackers.id, trackers.user_id, trackers.full_name, trackers.position,
                villages.name as village, villages.moo as village_moo,
                COUNT(logs.id) as recent')
            ->groupBy('trackers.id', 'trackers.user_id', 'trackers.full_name', 'trackers.position',
                      'villages.name', 'villages.moo')
            ->get();

        $inactiveTrackers = $trackerRows
            ->groupBy(fn ($r) => $r->user_id
                ? "user:{$r->user_id}"
                : "name:" . trim($r->full_name) . '|' . trim((string) $r->position))
            ->map(function ($group) {
                $first = $group->first();
                $villages = $group->sortBy('village')
                    ->map(fn ($r) => $r->village . ($r->village_moo ? " (ม.{$r->village_moo})" : ''))
                    ->unique()->values();
                return [
                    'id'            => (int) $first->id,
                    'name'          => $first->full_name,
                    'position'      => $first->position,
                    'village'       => $villages->first(),
                    'villages'      => $villages->all(),
                    'village_count' => $villages->count(),
                    'recent_logs'   => (int) $group->sum('recent'),
                ];
            })
            ->filter(fn ($p) => $p['recent_logs'] === 0)
            ->sortByDesc('village_count')
            ->values();

        $totalTargets = (int) Target::where('active', true)->count();
        $totalDone = (int) DB::table('target_current_status')->whereNotIn('status_code', ['4.1'])->count();
        $pct = $totalTargets > 0 ? round(($totalDone / $totalTargets) * 100, 2) : 0;

        ReportSnapshot::updateOrCreate(
            ['type' => 'weekly_bottleneck', 'snapshot_date' => $weekStart->toDateString()],
            [
                'week_num'         => (int) $weekStart->format('W'),
                'total_targets'    => $totalTargets,
                'total_registered' => $totalDone,
                'pct_done'         => $pct,
                'payload'          => [
                    'week' => ['start' => $weekStart->toDateString(), 'end' => $weekEnd->toDateString()],
                    'stuck_stages'      => $stuck,
                    'lagging_amphurs'   => $lagging,
                    'inactive_trackers' => $inactiveTrackers,
                ],
            ]
        );

        $this->info(sprintf(
            'Done: stuck=%d stages, lagging=%d amphurs, inactive trackers=%d',
            $stuck->count(), $lagging->count(), $inactiveTrackers->count()
        ));
        return self::SUCCESS;
    }
}

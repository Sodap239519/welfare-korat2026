<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\Target;
use App\Models\TargetCurrentStatus;
use App\Models\TargetStatusLog;
use App\Models\Tracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /** GET /api/dashboard/stats?amphur_id=&tambon_id=&village_id= */
    public function stats(Request $request): JsonResponse
    {
        $base = Target::query()->where('active', true);
        $this->applyScope($base, $request);

        $total = (int) $base->clone()->count();

        $byStatus = DB::table('targets')
            ->leftJoin('target_current_status', 'targets.id', '=', 'target_current_status.target_id')
            ->when($request->filled('amphur_id'),  fn ($q) => $q->where('targets.amphur_id',  (int) $request->amphur_id))
            ->when($request->filled('tambon_id'),  fn ($q) => $q->where('targets.tambon_id',  (int) $request->tambon_id))
            ->when($request->filled('village_id'), fn ($q) => $q->where('targets.village_id', (int) $request->village_id))
            ->where('targets.active', true)
            ->groupBy('target_current_status.status_code')
            ->selectRaw('COALESCE(target_current_status.status_code, "0") as code, COUNT(*) as n')
            ->pluck('n', 'code');

        $registered = (int) collect($byStatus)
            ->except(['0', '4.1'])->sum();
        $waitingKyc = (int) ($byStatus['4.6'] ?? 0);
        $kycDone    = (int) ($byStatus['4.7'] ?? 0);
        $stuck      = (int) (($byStatus['4.4'] ?? 0) + ($byStatus['4.5'] ?? 0));

        $today = TargetStatusLog::whereDate('changed_at', today())->count();

        return response()->json([
            'total'        => $total,
            'registered'   => $registered,
            'waiting_kyc'  => $waitingKyc,
            'kyc_done'     => $kycDone,
            'stuck'        => $stuck,
            'today_change' => $today,
            'by_status'    => $byStatus,
            'pct_done'     => $total > 0 ? round(($registered / $total) * 100, 1) : 0,
            'as_of'        => now()->toIso8601String(),
        ]);
    }

    /** GET /api/dashboard/trends?days=14 */
    public function trends(Request $request): JsonResponse
    {
        $days = max(7, min((int) $request->input('days', 14), 60));

        $rows = TargetStatusLog::query()
            ->whereDate('changed_at', '>=', now()->subDays($days))
            ->whereNotIn('status_code', ['4.1'])
            ->selectRaw('DATE(changed_at) as d, COUNT(*) as n')
            ->groupBy('d')
            ->pluck('n', 'd');

        $registered = (int) TargetCurrentStatus::whereNotIn('status_code', ['4.1'])->count();
        $baseline   = max(0, $registered - $rows->sum());
        $totalForTarget = (int) Target::where('active', true)->count();
        $dailyGoal  = $totalForTarget > 0 ? (int) round($totalForTarget * 0.75 / $days) : 0;

        $labels = $cumSeries = $tgtSeries = [];
        $cum = $baseline;
        $tCum = $baseline;

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $cum += (int) ($rows[$date] ?? 0);
            $tCum += $dailyGoal;
            $labels[]    = now()->subDays($i)->format('d');
            $cumSeries[] = $cum;
            $tgtSeries[] = $tCum;
        }

        return response()->json([
            'labels' => $labels,
            'series' => [
                ['name' => 'ยอดสะสม',  'data' => $cumSeries],
                ['name' => 'เป้าหมาย', 'data' => $tgtSeries],
            ],
        ]);
    }

    /** GET /api/dashboard/by-channel */
    public function byChannel(Request $request): JsonResponse
    {
        $rows = DB::table('target_current_status as tcs')
            ->leftJoin('channels', 'channels.id', '=', 'tcs.channel_id')
            ->join('targets', 'targets.id', '=', 'tcs.target_id')
            ->where('targets.active', true)
            ->when($request->filled('amphur_id'),  fn ($q) => $q->where('targets.amphur_id',  (int) $request->amphur_id))
            ->when($request->filled('tambon_id'),  fn ($q) => $q->where('targets.tambon_id',  (int) $request->tambon_id))
            ->when($request->filled('village_id'), fn ($q) => $q->where('targets.village_id', (int) $request->village_id))
            ->groupBy('channels.id', 'channels.name')
            ->orderBy('channels.sort_order')
            ->selectRaw('channels.name, COUNT(*) as n')
            ->get();

        $all = Channel::orderBy('sort_order')->pluck('name');
        $map = $rows->pluck('n', 'name');

        return response()->json([
            'labels' => $all->all(),
            'data'   => $all->map(fn ($n) => (int) ($map[$n] ?? 0))->all(),
        ]);
    }

    /** GET /api/dashboard/top-villages?limit=10 */
    public function topVillages(Request $request): JsonResponse
    {
        $limit = max(1, min((int) $request->input('limit', 10), 50));

        $rows = DB::table('villages')
            ->join('tambons', 'tambons.id', '=', 'villages.tambon_id')
            ->join('amphurs', 'amphurs.id', '=', 'tambons.amphur_id')
            ->leftJoin('targets', function ($j) {
                $j->on('targets.village_id', '=', 'villages.id')
                  ->where('targets.active', '=', true);
            })
            ->leftJoin('target_current_status as tcs', 'tcs.target_id', '=', 'targets.id')
            ->selectRaw('
                villages.id,
                villages.name as village,
                villages.moo,
                tambons.name as tambon,
                amphurs.name as amphur,
                COUNT(DISTINCT targets.id) as total,
                COUNT(DISTINCT CASE WHEN tcs.status_code IS NOT NULL AND tcs.status_code <> "4.1" THEN targets.id END) as done
            ')
            ->when($request->filled('amphur_id'),  fn ($q) => $q->where('amphurs.id', (int) $request->amphur_id))
            ->when($request->filled('tambon_id'),  fn ($q) => $q->where('tambons.id', (int) $request->tambon_id))
            ->groupBy('villages.id', 'villages.name', 'villages.moo', 'tambons.name', 'amphurs.name')
            ->orderByRaw('COUNT(DISTINCT CASE WHEN tcs.status_code IS NOT NULL AND tcs.status_code <> "4.1" THEN targets.id END) / GREATEST(COUNT(DISTINCT targets.id), 1) DESC')
            ->limit($limit)
            ->get();

        $villageIds = $rows->pluck('id');
        $trackers = Tracker::whereIn('village_id', $villageIds)
            ->where('active', true)
            ->orderBy('id')
            ->get()
            ->groupBy('village_id');

        return response()->json([
            'data' => $rows->map(function ($r) use ($trackers) {
                $t = $trackers->get($r->id)?->first();
                $pct = $r->total > 0 ? round(($r->done / $r->total) * 100) : 0;
                return [
                    'village_id' => $r->id,
                    'village'    => $r->village,
                    'moo'        => $r->moo,
                    'location'   => $r->tambon . ' · ' . $r->amphur,
                    'total'      => (int) $r->total,
                    'done'       => (int) $r->done,
                    'pct'        => $pct,
                    'tracker'    => $t ? ['name' => $t->full_name, 'position' => $t->position] : null,
                ];
            }),
        ]);
    }

    private function applyScope($query, Request $request): void
    {
        $query->when($request->filled('amphur_id'),  fn ($q) => $q->where('amphur_id',  (int) $request->amphur_id))
              ->when($request->filled('tambon_id'),  fn ($q) => $q->where('tambon_id',  (int) $request->tambon_id))
              ->when($request->filled('village_id'), fn ($q) => $q->where('village_id', (int) $request->village_id));
    }
}

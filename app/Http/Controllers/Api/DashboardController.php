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
            ->where('targets.active', true);
        $this->applyScope($byStatus, $request, 'targets');
        $byStatus = $byStatus
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

    /** GET /api/dashboard/trends?days=14 | from=YYYY-MM-DD&to=YYYY-MM-DD  (optional amphur_id/tambon_id/village_id) */
    public function trends(Request $request): JsonResponse
    {
        // 1) Resolve date window (from/to override days)
        if ($request->filled('from') && $request->filled('to')) {
            $from = \Carbon\Carbon::parse($request->input('from'))->startOfDay();
            $to   = \Carbon\Carbon::parse($request->input('to'))->endOfDay();
            if ($to->lt($from)) [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
            $days = max(1, $from->diffInDays($to) + 1);
        } else {
            $days = max(1, min((int) $request->input('days', 14), 365));
            $from = now()->subDays($days - 1)->startOfDay();
            $to   = now()->endOfDay();
        }

        // 2) Scope: filter status logs to targets in selected amphur/tambon/village
        $scopedTargetIds = Target::query()->where('active', true)
            ->when($request->filled('amphur_id'),  fn ($q) => $q->where('amphur_id',  (int) $request->amphur_id))
            ->when($request->filled('tambon_id'),  fn ($q) => $q->where('tambon_id',  (int) $request->tambon_id))
            ->when($request->filled('village_id'), fn ($q) => $q->where('village_id', (int) $request->village_id))
            ->pluck('id');

        $hasScope = $request->filled('amphur_id') || $request->filled('tambon_id') || $request->filled('village_id');

        $logsQ = TargetStatusLog::query()
            ->whereBetween('changed_at', [$from, $to])
            ->whereNotIn('status_code', ['4.1']);
        if ($hasScope) $logsQ->whereIn('target_id', $scopedTargetIds);

        $rows = $logsQ->selectRaw('DATE(changed_at) as d, COUNT(*) as n')
            ->groupBy('d')
            ->pluck('n', 'd');

        // 3) Baseline = ลทบ.ก่อนช่วงนี้ (scoped)
        $registeredQ = TargetCurrentStatus::query()->whereNotIn('status_code', ['4.1']);
        if ($hasScope) $registeredQ->whereIn('target_id', $scopedTargetIds);
        $registered = (int) $registeredQ->count();
        $baseline   = max(0, $registered - $rows->sum());

        $totalForTarget = $hasScope ? $scopedTargetIds->count() : (int) Target::where('active', true)->count();
        $dailyGoal  = $totalForTarget > 0 ? (int) round($totalForTarget * 0.75 / max($days, 1)) : 0;

        $labels = $cumSeries = $tgtSeries = [];
        $cum = $baseline;
        $tCum = $baseline;

        $cursor = $from->copy();
        for ($i = 0; $i < $days; $i++) {
            $date = $cursor->toDateString();
            $cum += (int) ($rows[$date] ?? 0);
            $tCum += $dailyGoal;
            $labels[]    = $cursor->format('d/m');
            $cumSeries[] = $cum;
            $tgtSeries[] = $tCum;
            $cursor->addDay();
        }

        return response()->json([
            'labels' => $labels,
            'from'   => $from->toDateString(),
            'to'     => $to->toDateString(),
            'days'   => $days,
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

    /**
     * GET /api/dashboard/top?level=amphur|tambon|village&limit=10&amphur_id=&tambon_id=
     * คืนค่า TOP N แยกตามระดับ พร้อม breakdown 7 สถานะ (4.1-4.7)
     */
    public function top(Request $request): JsonResponse
    {
        $level = $request->input('level', 'village');
        // cap 1000 — รองรับ "ทุกอำเภอ" (32) และ "ทุกตำบล" (~312) บน chart ภาพรวม
        $limit = max(1, min((int) $request->input('limit', 10), 1000));

        $groupCols = match ($level) {
            'amphur'  => ['amphurs.id as gid', 'amphurs.name as name', DB::raw('NULL as sub_location')],
            'tambon'  => ['tambons.id as gid', 'tambons.name as name', 'amphurs.name as sub_location'],
            default   => ['villages.id as gid', 'villages.name as name', DB::raw("CONCAT(tambons.name, ' · ', amphurs.name) as sub_location"), 'villages.moo'],
        };

        $q = DB::table('targets')
            ->join('villages', 'villages.id', '=', 'targets.village_id')
            ->join('tambons',  'tambons.id',  '=', 'villages.tambon_id')
            ->join('amphurs',  'amphurs.id',  '=', 'tambons.amphur_id')
            ->leftJoin('target_current_status as tcs', 'tcs.target_id', '=', 'targets.id')
            ->where('targets.active', true)
            ->when($request->filled('amphur_id'),  fn ($x) => $x->where('amphurs.id', (int) $request->amphur_id))
            ->when($request->filled('tambon_id'),  fn ($x) => $x->where('tambons.id', (int) $request->tambon_id))
            ->select($groupCols)
            ->selectRaw('
                COUNT(DISTINCT targets.id) as total,
                COUNT(DISTINCT CASE WHEN tcs.status_code IS NOT NULL AND tcs.status_code <> "4.1" THEN targets.id END) as done,
                SUM(CASE WHEN tcs.status_code IS NULL THEN 1 ELSE 0 END) as s_0,
                SUM(CASE WHEN tcs.status_code = "4.1" THEN 1 ELSE 0 END) as s_41,
                SUM(CASE WHEN tcs.status_code = "4.2" THEN 1 ELSE 0 END) as s_42,
                SUM(CASE WHEN tcs.status_code = "4.3" THEN 1 ELSE 0 END) as s_43,
                SUM(CASE WHEN tcs.status_code = "4.4" THEN 1 ELSE 0 END) as s_44,
                SUM(CASE WHEN tcs.status_code = "4.5" THEN 1 ELSE 0 END) as s_45,
                SUM(CASE WHEN tcs.status_code = "4.6" THEN 1 ELSE 0 END) as s_46,
                SUM(CASE WHEN tcs.status_code = "4.7" THEN 1 ELSE 0 END) as s_47
            ');

        $q = match ($level) {
            'amphur'  => $q->groupBy('amphurs.id', 'amphurs.name'),
            'tambon'  => $q->groupBy('tambons.id', 'tambons.name', 'amphurs.name'),
            default   => $q->groupBy('villages.id', 'villages.name', 'villages.moo', 'tambons.name', 'amphurs.name'),
        };

        $rows = $q->orderByRaw('
                COUNT(DISTINCT CASE WHEN tcs.status_code IS NOT NULL AND tcs.status_code <> "4.1" THEN targets.id END)
                / GREATEST(COUNT(DISTINCT targets.id), 1) DESC
            ')
            ->limit($limit)
            ->get();

        return response()->json([
            'level' => $level,
            'data'  => $rows->map(function ($r) use ($level) {
                $total = (int) $r->total;
                $done  = (int) $r->done;
                return [
                    'id'       => (int) $r->gid,
                    'name'     => $r->name . ($level === 'village' && !empty($r->moo) ? ' · หมู่ ' . $r->moo : ''),
                    'location' => $r->sub_location ?? null,
                    'total'    => $total,
                    'done'     => $done,
                    'pct'      => $total > 0 ? round(($done / $total) * 100) : 0,
                    'by_status' => [
                        '0'   => (int) $r->s_0,
                        '4.1' => (int) $r->s_41,
                        '4.2' => (int) $r->s_42,
                        '4.3' => (int) $r->s_43,
                        '4.4' => (int) $r->s_44,
                        '4.5' => (int) $r->s_45,
                        '4.6' => (int) $r->s_46,
                        '4.7' => (int) $r->s_47,
                    ],
                ];
            }),
        ]);
    }

    private function applyScope($query, Request $request, string $tablePrefix = ''): void
    {
        $prefix = $tablePrefix ? "{$tablePrefix}." : '';
        $query->when($request->filled('amphur_id'),  fn ($q) => $q->where($prefix.'amphur_id',  (int) $request->amphur_id))
              ->when($request->filled('tambon_id'),  fn ($q) => $q->where($prefix.'tambon_id',  (int) $request->tambon_id))
              ->when($request->filled('village_id'), fn ($q) => $q->where($prefix.'village_id', (int) $request->village_id));

        // Auto-scope สำหรับ tracker — เห็นเฉพาะหมู่บ้าน/ตำบล/อำเภอที่อยู่ใน user_scopes
        $user = $request->user();
        if ($user && !$user->hasRole('super_admin') && !$user->hasRole('admin')) {
            $scopes = \App\Models\UserScope::where('user_id', $user->id)->get();
            if ($scopes->isEmpty()) {
                // ไม่มี scope → บล็อกทุกอย่าง
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function ($w) use ($scopes, $prefix) {
                    foreach ($scopes as $s) {
                        $col = match ($s->scope_type) {
                            'village' => $prefix.'village_id',
                            'tambon'  => $prefix.'tambon_id',
                            'amphur'  => $prefix.'amphur_id',
                        };
                        $w->orWhere($col, $s->scope_id);
                    }
                });
            }
        }
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Amphur;
use App\Models\Channel;
use App\Models\ProjectPhase;
use App\Models\RegistrationStatus;
use App\Models\Tambon;
use App\Models\Village;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReferenceController extends Controller
{
    public function statuses(): JsonResponse
    {
        return response()->json([
            'data' => RegistrationStatus::orderBy('sort_order')->get(['code', 'label', 'color', 'requires_note', 'requires_channel']),
        ]);
    }

    public function channels(): JsonResponse
    {
        return response()->json([
            'data' => Channel::orderBy('sort_order')->get(['id', 'code', 'name', 'icon']),
        ]);
    }

    public function banks(): JsonResponse
    {
        return response()->json([
            'data' => collect(config('banks.banks', []))->map(fn ($name, $code) => [
                'code' => $code,
                'name' => $name,
            ])->values(),
        ]);
    }

    public function amphurs(): JsonResponse
    {
        return response()->json([
            'data' => Amphur::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function tambons(Request $request): JsonResponse
    {
        $q = Tambon::orderBy('name');
        if ($request->filled('amphur_id')) $q->where('amphur_id', (int) $request->amphur_id);
        return response()->json(['data' => $q->get(['id', 'name', 'amphur_id'])]);
    }

    public function villages(Request $request): JsonResponse
    {
        $q = Village::orderBy('name');
        if ($request->filled('tambon_id')) $q->where('tambon_id', (int) $request->tambon_id);
        return response()->json(['data' => $q->get(['id', 'name', 'moo', 'tambon_id'])]);
    }

    public function projectPhases(): JsonResponse
    {
        return response()->json([
            'data' => ProjectPhase::orderBy('sort_order')->get(['id', 'name', 'sop_level', 'icon', 'description', 'is_current']),
        ]);
    }

    /** POST /api/admin/phases/{id}/set-current — Super Admin only (route middleware) */
    public function setCurrentPhase(int $id): JsonResponse
    {
        $phase = ProjectPhase::findOrFail($id);

        DB::transaction(function () use ($phase) {
            ProjectPhase::query()->update(['is_current' => false]);
            $phase->update(['is_current' => true]);
        });

        activity('sop_phase')
            ->causedBy(auth()->user())
            ->performedOn($phase)
            ->log("ตั้งขั้น SOP ปัจจุบันเป็น ชั้น {$phase->sop_level} — {$phase->name}");

        return response()->json([
            'message' => "ตั้งขั้นปัจจุบันเป็น ชั้น {$phase->sop_level} เรียบร้อย",
            'phase'   => $phase->fresh(['id', 'sop_level', 'name', 'is_current']),
        ]);
    }

    /** Quick aggregate for Overview page — รับ filter ?amphur_id=&tambon_id=&village_id= */
    public function overviewMetrics(Request $request): JsonResponse
    {
        $applyScope = fn ($q) => $q
            ->when($request->filled('amphur_id'),  fn ($x) => $x->where('targets.amphur_id',  (int) $request->amphur_id))
            ->when($request->filled('tambon_id'),  fn ($x) => $x->where('targets.tambon_id',  (int) $request->tambon_id))
            ->when($request->filled('village_id'), fn ($x) => $x->where('targets.village_id', (int) $request->village_id));

        $rows = $applyScope(
                DB::table('target_current_status as tcs')
                    ->join('targets', 'targets.id', '=', 'tcs.target_id')
                    ->where('targets.active', true)
            )
            ->selectRaw('tcs.status_code, COUNT(*) as n')
            ->groupBy('tcs.status_code')
            ->pluck('n', 'status_code');

        $total = (int) \App\Models\Target::query()->where('active', true)
            ->when($request->filled('amphur_id'),  fn ($q) => $q->where('amphur_id',  (int) $request->amphur_id))
            ->when($request->filled('tambon_id'),  fn ($q) => $q->where('tambon_id',  (int) $request->tambon_id))
            ->when($request->filled('village_id'), fn ($q) => $q->where('village_id', (int) $request->village_id))
            ->count();
        $registered = (int) collect($rows)->except(['4.1'])->sum();

        return response()->json([
            'total'      => $total,
            'registered' => $registered,
            'pct_done'   => $total > 0 ? round(($registered / $total) * 100, 1) : 0,
            'level_4' => [
                'check_rights' => (int) ($rows['4.3'] ?? 0),
                'extra_docs'   => (int) ($rows['4.4'] ?? 0),
                'kyc_waiting'  => (int) ($rows['4.6'] ?? 0),
                'kyc_done'     => (int) ($rows['4.7'] ?? 0),
            ],
            'by_channel' => $applyScope(
                    DB::table('target_current_status as tcs')
                        ->join('targets', 'targets.id', '=', 'tcs.target_id')
                        ->leftJoin('channels', 'channels.id', '=', 'tcs.channel_id')
                        ->where('targets.active', true)
                        ->whereNotNull('channels.name')
                )
                ->groupBy('channels.name')
                ->pluck(DB::raw('COUNT(*)'), 'channels.name'),
            'by_bank'    => $this->byBankBreakdown($request),
        ]);
    }

    private function byBankBreakdown(Request $request): array
    {
        $bankCh = \App\Models\Channel::where('code', 'bank')->first();
        if (!$bankCh) return [];

        $counts = DB::table('target_current_status as tcs')
            ->join('targets', 'targets.id', '=', 'tcs.target_id')
            ->where('targets.active', true)
            ->where('tcs.channel_id', $bankCh->id)
            ->whereNotNull('tcs.sub_channel')
            ->when($request->filled('amphur_id'),  fn ($q) => $q->where('targets.amphur_id',  (int) $request->amphur_id))
            ->when($request->filled('tambon_id'),  fn ($q) => $q->where('targets.tambon_id',  (int) $request->tambon_id))
            ->when($request->filled('village_id'), fn ($q) => $q->where('targets.village_id', (int) $request->village_id))
            ->groupBy('tcs.sub_channel')
            ->pluck(DB::raw('COUNT(*)'), 'tcs.sub_channel');

        $banks = config('banks.banks', []);
        $result = [];
        foreach ($banks as $code => $name) {
            $result[] = [
                'code'  => $code,
                'name'  => $name,
                'count' => (int) ($counts[$code] ?? 0),
            ];
        }
        return $result;
    }
}

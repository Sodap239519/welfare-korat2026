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

    /** Quick aggregate for Overview page (ชั้น 4 — 3 ฝ่าย) */
    public function overviewMetrics(): JsonResponse
    {
        $rows = DB::table('target_current_status')
            ->selectRaw('status_code, COUNT(*) as n')
            ->groupBy('status_code')
            ->pluck('n', 'status_code');

        $total = (int) \App\Models\Target::where('active', true)->count();
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
            'by_channel' => DB::table('target_current_status as tcs')
                ->leftJoin('channels', 'channels.id', '=', 'tcs.channel_id')
                ->whereNotNull('channels.name')
                ->groupBy('channels.name')
                ->pluck(DB::raw('COUNT(*)'), 'channels.name'),
            'by_bank'    => $this->byBankBreakdown(),
        ]);
    }

    private function byBankBreakdown(): array
    {
        $bankCh = \App\Models\Channel::where('code', 'bank')->first();
        if (!$bankCh) return [];

        $counts = DB::table('target_current_status')
            ->where('channel_id', $bankCh->id)
            ->whereNotNull('sub_channel')
            ->groupBy('sub_channel')
            ->pluck(DB::raw('COUNT(*)'), 'sub_channel');

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

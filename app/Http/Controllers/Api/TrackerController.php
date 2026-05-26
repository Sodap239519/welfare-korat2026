<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Target;
use App\Models\TargetCurrentStatus;
use App\Models\Tracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TrackerController extends Controller
{
    /** GET /api/trackers — list with village + stats */
    public function index(Request $request): JsonResponse
    {
        $q = Tracker::query()
            ->with(['village:id,name,moo,tambon_id', 'village.tambon:id,name,amphur_id', 'village.tambon.amphur:id,name'])
            ->where('active', true);

        if ($request->filled('q')) {
            $term = trim($request->q);
            $q->where(fn ($w) => $w->where('full_name', 'like', "%$term%")->orWhere('phone', 'like', "%$term%"));
        }
        if ($request->filled('position')) {
            $q->where('position', $request->position);
        }
        if ($request->filled('village_id')) {
            $q->where('village_id', (int) $request->village_id);
        }

        $trackers = $q->orderBy('id')->paginate((int) $request->input('per_page', 50))->withQueryString();

        $villageIds = $trackers->getCollection()->pluck('village_id')->unique();
        $stats = DB::table('targets')
            ->leftJoin('target_current_status as tcs', 'tcs.target_id', '=', 'targets.id')
            ->whereIn('targets.village_id', $villageIds)
            ->where('targets.active', true)
            ->selectRaw('
                targets.village_id,
                COUNT(*) as total,
                COUNT(CASE WHEN tcs.status_code IS NOT NULL AND tcs.status_code <> "4.1" THEN 1 END) as done
            ')
            ->groupBy('targets.village_id')
            ->get()
            ->keyBy('village_id');

        $trackers->getCollection()->transform(function ($t) use ($stats) {
            $s = $stats->get($t->village_id);
            $total = (int) ($s->total ?? 0);
            $done  = (int) ($s->done  ?? 0);
            return [
                'id'             => $t->id,
                'full_name'      => $t->full_name,
                'position'       => $t->position,
                'position_other' => $t->position_other,
                'phone'          => $t->phone,
                'village_id'     => $t->village_id,
                'village'        => $t->village?->name,
                'moo'            => $t->village?->moo,
                'tambon'         => $t->village?->tambon?->name,
                'tambon_id'      => $t->village?->tambon_id,
                'amphur'         => $t->village?->tambon?->amphur?->name,
                'amphur_id'      => $t->village?->tambon?->amphur_id,
                'total'          => $total,
                'done'           => $done,
                'pct'            => $total > 0 ? round(($done / $total) * 100) : 0,
            ];
        });

        return response()->json($trackers);
    }

    /** POST /api/trackers */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'full_name'      => ['required', 'string', 'max:150'],
            'position'       => ['required', 'string', 'max:40'],
            'position_other' => ['nullable', 'string', 'max:100'],
            'phone'          => ['nullable', 'string', 'max:20'],
            'village_id'     => ['required', 'integer', 'exists:villages,id'],
        ]);
        $tracker = Tracker::create($data + ['active' => true]);
        return response()->json(['data' => $tracker], 201);
    }

    /** PATCH /api/trackers/{id} */
    public function update(Request $request, int $id): JsonResponse
    {
        $tracker = Tracker::findOrFail($id);
        $data = $request->validate([
            'full_name'      => ['sometimes', 'string', 'max:150'],
            'position'       => ['sometimes', 'string', 'max:40'],
            'position_other' => ['nullable', 'string', 'max:100'],
            'phone'          => ['nullable', 'string', 'max:20'],
            'village_id'     => ['sometimes', 'integer', 'exists:villages,id'],
            'active'         => ['sometimes', 'boolean'],
        ]);
        $tracker->update($data);
        return response()->json(['data' => $tracker]);
    }

    /** DELETE /api/trackers/{id} (soft = active=false) */
    public function destroy(int $id): JsonResponse
    {
        $tracker = Tracker::findOrFail($id);
        $tracker->update(['active' => false]);
        return response()->json(['message' => 'Tracker deactivated']);
    }
}

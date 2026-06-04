<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    /** GET /api/admin/activity */
    public function index(Request $request): JsonResponse
    {
        $q = Activity::with('causer:id,name')->orderByDesc('id');

        if ($request->filled('q')) {
            $term = trim($request->q);
            $q->where(fn ($w) => $w->where('description', 'like', "%$term%")
                                  ->orWhere('log_name', 'like', "%$term%"));
        }
        // ตัวกรองประเภท (multi-select) — รวมทั้ง event (created/updated/deleted) และ log_name (settings/sop_phase/village_coords)
        if ($request->filled('types')) {
            $types = (array) $request->input('types');
            $events   = array_values(array_intersect($types, ['created', 'updated', 'deleted']));
            $logNames = array_values(array_diff($types, ['created', 'updated', 'deleted']));
            $q->where(function ($w) use ($events, $logNames) {
                if ($events)   $w->orWhereIn('event', $events);
                if ($logNames) $w->orWhereIn('log_name', $logNames);
            });
        }
        if ($request->filled('date')) {
            $q->whereDate('created_at', $request->date);
        }

        $perPage = max(10, min((int) $request->input('per_page', 50), 200));
        $page = $q->paginate($perPage)->withQueryString();

        $page->getCollection()->transform(fn ($a) => [
            'id'          => $a->id,
            'log_name'    => $a->log_name,
            'description' => $a->description,
            'event'       => $a->event,
            'subject'     => $a->subject_type ? class_basename($a->subject_type).'#'.$a->subject_id : null,
            'causer'      => $a->causer?->name,
            'properties'  => $a->properties,
            'created_at'  => $a->created_at?->toIso8601String(),
        ]);

        return response()->json($page);
    }

    public function stats(): JsonResponse
    {
        return response()->json([
            'today'        => Activity::whereDate('created_at', today())->count(),
            'this_week'    => Activity::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'login_fail'   => 0,
        ]);
    }
}

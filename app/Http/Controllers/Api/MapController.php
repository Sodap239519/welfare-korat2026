<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MapController extends Controller
{
    /** GET /api/map/villages — villages with coordinates + registration stats */
    public function villages(Request $request): JsonResponse
    {
        $rows = DB::table('villages')
            ->join('tambons', 'tambons.id', '=', 'villages.tambon_id')
            ->join('amphurs', 'amphurs.id', '=', 'tambons.amphur_id')
            ->leftJoin('targets', function ($j) {
                $j->on('targets.village_id', '=', 'villages.id')->where('targets.active', '=', true);
            })
            ->leftJoin('target_current_status as tcs', 'tcs.target_id', '=', 'targets.id')
            ->whereNotNull('villages.lat')
            ->whereNotNull('villages.lng')
            ->when($request->filled('amphur_id'),  fn ($q) => $q->where('amphurs.id', (int) $request->amphur_id))
            ->when($request->filled('tambon_id'),  fn ($q) => $q->where('tambons.id', (int) $request->tambon_id))
            ->selectRaw('
                villages.id, villages.name, villages.moo, villages.lat, villages.lng,
                tambons.id as tambon_id, tambons.name as tambon,
                amphurs.id as amphur_id, amphurs.name as amphur,
                COUNT(DISTINCT targets.id) as total,
                COUNT(DISTINCT CASE WHEN tcs.status_code IS NOT NULL AND tcs.status_code <> "4.1" THEN targets.id END) as done,
                COUNT(DISTINCT CASE WHEN tcs.status_code = "4.7" THEN targets.id END) as kyc_done
            ')
            ->groupBy('villages.id', 'villages.name', 'villages.moo', 'villages.lat', 'villages.lng', 'tambons.id', 'tambons.name', 'amphurs.id', 'amphurs.name')
            ->get();

        return response()->json([
            'data' => $rows->map(function ($r) {
                $pct = $r->total > 0 ? round(($r->done / $r->total) * 100) : 0;
                return [
                    'id'         => (int) $r->id,
                    'name'       => $r->name . ($r->moo ? ' (ม.'.$r->moo.')' : ''),
                    'tambon_id'  => (int) $r->tambon_id,
                    'tambon'     => $r->tambon,
                    'amphur_id'  => (int) $r->amphur_id,
                    'amphur'     => $r->amphur,
                    'lat'        => (float) $r->lat,
                    'lng'        => (float) $r->lng,
                    'total'      => (int) $r->total,
                    'done'       => (int) $r->done,
                    'kyc_done'   => (int) $r->kyc_done,
                    'pct'        => $pct,
                ];
            }),
        ]);
    }
}

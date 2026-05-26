<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Village;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VillageController extends Controller
{
    /**
     * PATCH /api/villages/{id}/coords — Super Admin ปรับพิกัดบนแผนที่
     */
    public function updateCoords(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $village = Village::findOrFail($id);
        $village->update($data);

        activity('village_coords')
            ->causedBy($request->user())
            ->performedOn($village)
            ->withProperties(['lat' => $data['lat'], 'lng' => $data['lng']])
            ->log("ปรับพิกัดหมู่บ้าน {$village->name} (ม.{$village->moo})");

        return response()->json([
            'message' => 'บันทึกพิกัดใหม่เรียบร้อย',
            'lat' => (float) $village->lat,
            'lng' => (float) $village->lng,
        ]);
    }
}

<?php

namespace Database\Seeders;

use App\Models\Village;
use Illuminate\Database\Seeder;

/**
 * แก้พิกัดหมู่บ้านที่วางผิด (เคยตกไป fallback ระดับจังหวัด → กองที่เมือง)
 * รันหลัง AmphurCoordsSeeder (ต้องมีพิกัดอำเภอก่อน)
 *
 *   1) หมู่บ้านที่ห่างจากศูนย์กลางอำเภอตัวเอง > ~25 กม. = วางผิด → ล้างพิกัดเป็น null
 *   2) เติมพิกัดใหม่ให้หมู่บ้านที่ไม่มีพิกัด → ensureCoords() ใช้พิกัดอำเภอจริง (ถูกอำเภอ)
 *
 * ไม่แตะหมู่บ้านที่วางถูกอยู่แล้ว (ลากตั้งเอง / อยู่ในเขตอำเภอ)
 */
class FixVillageCoordsSeeder extends Seeder
{
    public function run(): void
    {
        $threshold = 0.25;   // องศา ~25 กม.
        $cleared = 0;

        // 1) ล้างพิกัดหมู่บ้านที่ห่างจากอำเภอตัวเองเกินไป
        Village::with('tambon.amphur')->whereNotNull('lat')->chunkById(500, function ($vs) use ($threshold, &$cleared) {
            foreach ($vs as $v) {
                $am = $v->tambon?->amphur;
                if (!$am || $am->lat === null) continue;
                $d = sqrt(pow($v->lat - $am->lat, 2) + pow($v->lng - $am->lng, 2));
                if ($d > $threshold) {
                    $v->lat = null;
                    $v->lng = null;
                    $v->save();
                    $cleared++;
                }
            }
        });

        // 2) เติมพิกัดใหม่ให้หมู่บ้านที่ไม่มีพิกัด (ใช้พิกัดอำเภอจริง)
        $filled = 0;
        Village::whereNull('lat')->chunkById(500, function ($vs) use (&$filled) {
            foreach ($vs as $v) {
                $v->ensureCoords();
                $filled++;
            }
        });

        $this->command->info("FixVillageCoords: ล้างที่วางผิด {$cleared} หมู่บ้าน · เติมใหม่ {$filled} หมู่บ้าน");
    }
}

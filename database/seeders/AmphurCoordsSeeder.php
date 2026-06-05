<?php

namespace Database\Seeders;

use App\Models\Amphur;
use App\Support\AmphurCoords;
use Illuminate\Database\Seeder;

/**
 * เติมพิกัดศูนย์กลาง 32 อำเภอ ลงตาราง amphurs (idempotent)
 * เพื่อให้ Village::ensureCoords() วางหมู่บ้านได้ถูกอำเภอ (ไม่ตกไป fallback ระดับจังหวัด)
 */
class AmphurCoordsSeeder extends Seeder
{
    public function run(): void
    {
        $n = 0;
        foreach (AmphurCoords::COORDS as $name => [$lat, $lng]) {
            $n += Amphur::where('name', $name)->update(['lat' => $lat, 'lng' => $lng]);
        }
        $this->command->info("AmphurCoords: เติมพิกัด {$n} อำเภอ");
    }
}

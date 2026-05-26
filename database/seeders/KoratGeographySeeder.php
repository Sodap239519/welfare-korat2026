<?php

namespace Database\Seeders;

use App\Models\Amphur;
use App\Models\Tambon;
use Illuminate\Database\Seeder;

/**
 * Seed อำเภอ + ตำบล ทั้งหมดของจังหวัดนครราชสีมา (32 อำเภอ + ~312 ตำบล)
 *
 * Data source: kongvut/thai-province-data
 *   api/latest/province_with_district_and_sub_district.json
 * (เก็บไว้ที่ database/data/thai_all.json)
 *
 * idempotent: updateOrCreate by (name) — existing rows ถูก preserve
 *   พร้อมเติม code field
 */
class KoratGeographySeeder extends Seeder
{
    private const PROVINCE_NAME_TH = 'นครราชสีมา';
    private const DATA_FILE = 'data/thai_all.json';

    public function run(): void
    {
        $path = database_path(self::DATA_FILE);
        if (!file_exists($path)) {
            $this->command->warn("Data file not found: $path");
            $this->command->warn('Download with: curl -L https://raw.githubusercontent.com/kongvut/thai-province-data/master/api/latest/province_with_district_and_sub_district.json -o database/data/thai_all.json');
            return;
        }

        $raw = json_decode(file_get_contents($path), true);
        $province = null;
        foreach ($raw as $p) {
            if (($p['name_th'] ?? '') === self::PROVINCE_NAME_TH) {
                $province = $p;
                break;
            }
        }

        if (!$province) {
            $this->command->error('Province not found: '.self::PROVINCE_NAME_TH);
            return;
        }

        $amphurCount = 0;
        $tambonCount = 0;
        $tambonExist = 0;

        foreach ($province['districts'] as $district) {
            $amphur = Amphur::updateOrCreate(
                ['name' => $district['name_th']],
                ['code' => (string) $district['id']]
            );
            $amphurCount++;

            foreach (($district['sub_districts'] ?? []) as $sub) {
                $found = Tambon::where('amphur_id', $amphur->id)
                    ->where('name', $sub['name_th'])
                    ->first();
                if ($found) {
                    $found->update(['code' => (string) $sub['id']]);
                    $tambonExist++;
                } else {
                    Tambon::create([
                        'amphur_id' => $amphur->id,
                        'name'      => $sub['name_th'],
                        'code'      => (string) $sub['id'],
                    ]);
                    $tambonCount++;
                }
            }
        }

        $this->command->info(sprintf(
            'Seeded geography: %d amphurs, %d tambons created (%d existing updated)',
            $amphurCount, $tambonCount, $tambonExist
        ));
    }
}

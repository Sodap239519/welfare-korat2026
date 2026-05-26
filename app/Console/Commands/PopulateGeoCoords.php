<?php

namespace App\Console\Commands;

use App\Models\Amphur;
use App\Models\Tambon;
use Illuminate\Console\Command;

class PopulateGeoCoords extends Command
{
    protected $signature = 'geo:populate-coords {--province=นครราชสีมา : ชื่อจังหวัด}';
    protected $description = 'อ่าน database/data/thai_all.json แล้วเติม lat/lng ให้ amphurs + tambons ตามข้อมูลปกครองจริง';

    public function handle(): int
    {
        $file = database_path('data/thai_all.json');
        if (!file_exists($file)) {
            $this->error("ไม่พบไฟล์: $file");
            return self::FAILURE;
        }
        $province = $this->option('province');
        $json = json_decode(file_get_contents($file), true);

        $target = collect($json)->firstWhere('name_th', $province);
        if (!$target) {
            $this->error("ไม่พบจังหวัด $province ใน JSON");
            return self::FAILURE;
        }

        $aOk = $aSkip = $tOk = $tSkip = 0;
        foreach ($target['districts'] as $a) {
            $amphur = Amphur::where('name', $a['name_th'])->first();
            if (!$amphur) { $aSkip++; continue; }

            if (!empty($a['lat']) && !empty($a['long'])) {
                $amphur->update(['lat' => $a['lat'], 'lng' => $a['long']]);
                $aOk++;
            }

            foreach (($a['sub_districts'] ?? []) as $t) {
                $tambon = Tambon::where('amphur_id', $amphur->id)->where('name', $t['name_th'])->first();
                if (!$tambon) { $tSkip++; continue; }
                if (!empty($t['lat']) && !empty($t['long'])) {
                    $tambon->update(['lat' => $t['lat'], 'lng' => $t['long']]);
                    $tOk++;
                } else {
                    $tSkip++;
                }
            }
        }

        $this->info("✓ Amphur: อัปเดต $aOk · ข้าม $aSkip");
        $this->info("✓ Tambon: อัปเดต $tOk · ข้าม $tSkip (พิกัด null ใน JSON หรือไม่พบ)");
        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Village;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class GeocodeVillagesFromOsm extends Command
{
    protected $signature = 'villages:geocode-osm
        {--limit=50 : จำกัดจำนวน village ต่อรอบ (เลี่ยง rate limit)}
        {--tambon=* : เฉพาะ tambon_id ที่ระบุ}
        {--only-near-center : เฉพาะหมู่บ้านที่ยังอยู่ใกล้ tambon center (พิกัดน่าจะ fallback ไม่ใช่ของจริง)}
        {--dry : preview ไม่บันทึก}';
    protected $description = 'ค้นหาพิกัดจริงจาก OpenStreetMap (Nominatim) จากชื่อหมู่บ้าน + ตำบล + อำเภอ';

    public function handle(): int
    {
        $q = Village::with('tambon.amphur');
        $tambons = (array) $this->option('tambon');
        if (!empty($tambons)) $q->whereIn('tambon_id', $tambons);
        $rows = $q->get();

        // เฉพาะหมู่บ้านที่ยังใกล้ tambon center (พิกัด fallback)
        if ($this->option('only-near-center')) {
            $rows = $rows->filter(function ($v) {
                if (!$v->tambon?->lat) return false;
                $dLat = abs($v->lat - $v->tambon->lat);
                $dLng = abs($v->lng - $v->tambon->lng);
                return $dLat < 0.01 && $dLng < 0.01; // อยู่ใน ~1km ของ tambon center
            });
        }

        $rows = $rows->take((int) $this->option('limit'));
        if ($rows->isEmpty()) {
            $this->info('✓ ไม่มีหมู่บ้านต้อง geocode');
            return self::SUCCESS;
        }

        $this->info(sprintf('กำลังค้นหาจาก OSM Nominatim: %d หมู่บ้าน (rate limit 1 req/sec)', $rows->count()));
        $dry = (bool) $this->option('dry');
        $ok = $miss = 0;

        foreach ($rows as $v) {
            $tambonName = $v->tambon?->name;
            $amphurName = $v->tambon?->amphur?->name;
            if (!$tambonName || !$amphurName) {
                $miss++; continue;
            }

            // ลอง 3 รูปแบบ query — เจอแบบไหนเอาแบบนั้น
            $candidates = [
                "บ้าน{$v->name}, ตำบล{$tambonName}, อำเภอ{$amphurName}, นครราชสีมา",
                "{$v->name}, {$tambonName}, {$amphurName}, นครราชสีมา",
                "หมู่ {$v->moo} ตำบล{$tambonName} อำเภอ{$amphurName} นครราชสีมา",
            ];

            $found = null;
            foreach ($candidates as $i => $query) {
                try {
                    $resp = Http::withHeaders([
                            'User-Agent'      => 'WelfareKorat2026/1.0 (jetsada.dev@gmail.com)',
                            'Accept-Language' => 'th',
                        ])
                        ->timeout(15)
                        ->get('https://nominatim.openstreetmap.org/search', [
                            'q'            => $query,
                            'format'       => 'json',
                            'limit'        => 1,
                            'countrycodes' => 'th',
                        ]);
                    $hits = $resp->json();
                    if (!empty($hits) && isset($hits[0]['lat'])) {
                        $found = $hits[0];
                        break;
                    }
                } catch (\Throwable $e) {
                    // ลองรูปแบบถัดไป
                }
                sleep(1); // rate limit แม้แต่ระหว่างการลอง
            }

            $where = sprintf('ต.%s อ.%s', $tambonName, $amphurName);
            if ($found) {
                $newLat = (float) $found['lat'];
                $newLng = (float) $found['lon'];
                $this->line(sprintf('  ✓ #%-4d %-20s %s → [%.5f, %.5f]',
                    $v->id, mb_strimwidth($v->name, 0, 18, '…'), $where, $newLat, $newLng));
                if (!$dry) $v->update(['lat' => $newLat, 'lng' => $newLng]);
                $ok++;
            } else {
                $this->line(sprintf('  ✗ #%-4d %-20s %s — ไม่พบ',
                    $v->id, mb_strimwidth($v->name, 0, 18, '…'), $where));
                $miss++;
            }
        }

        $this->newLine();
        $this->info(sprintf('✓ พบ: %d · ไม่พบ: %d', $ok, $miss));
        if ($dry) $this->warn('DRY RUN — ไม่ได้บันทึก');
        return self::SUCCESS;
    }
}

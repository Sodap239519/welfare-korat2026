<?php

namespace App\Console\Commands;

use App\Models\Amphur;
use App\Models\Tambon;
use App\Models\Village;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-shot fix สำหรับหมุดในแผนที่ที่อยู่ผิดที่
 *
 * ทำงาน 3 ขั้น:
 *   1) เติม lat/lng ให้ amphurs + tambons จาก thai_all.json
 *   2) เติม amphur lat/lng จาก centroid ของ tambons (กรณี JSON มี null เช่น แก้งสนามนาง)
 *   3) Force-recompute พิกัด villages ทุกหมู่บ้านที่อยู่ห่าง tambon center > 5km
 */
class GeoFixAll extends Command
{
    protected $signature = 'geo:fix-all
        {--province=นครราชสีมา : ชื่อจังหวัด}
        {--dry : preview ไม่บันทึก}';

    protected $description = 'แก้พิกัดทั้งหมดในครั้งเดียว: amphurs/tambons จาก JSON + villages ที่ผิดที่';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');
        $province = $this->option('province');

        // ─────────── STEP 1: amphurs + tambons จาก JSON ───────────
        $this->info('▶ STEP 1: เติมพิกัด amphurs + tambons จาก thai_all.json');
        $file = database_path('data/thai_all.json');
        if (!file_exists($file)) {
            $this->error("ไม่พบไฟล์: $file");
            return self::FAILURE;
        }
        $json = json_decode(file_get_contents($file), true);
        $target = collect($json)->firstWhere('name_th', $province);
        if (!$target) {
            $this->error("ไม่พบจังหวัด $province ใน JSON");
            return self::FAILURE;
        }

        $aOk = $tOk = 0;
        foreach ($target['districts'] as $a) {
            $amphur = Amphur::where('name', $a['name_th'])->first();
            if (!$amphur) continue;

            if (!empty($a['lat']) && !empty($a['long'])) {
                if (!$dry) $amphur->update(['lat' => $a['lat'], 'lng' => $a['long']]);
                $aOk++;
            }

            foreach (($a['sub_districts'] ?? []) as $t) {
                $tambon = Tambon::where('amphur_id', $amphur->id)->where('name', $t['name_th'])->first();
                if (!$tambon) continue;
                if (!empty($t['lat']) && !empty($t['long'])) {
                    if (!$dry) $tambon->update(['lat' => $t['lat'], 'lng' => $t['long']]);
                    $tOk++;
                }
            }
        }
        $this->line("  ✓ amphurs อัปเดต: $aOk · tambons อัปเดต: $tOk");

        // ─── STEP 2: เติม amphur ที่ JSON เป็น null จาก centroid ของ tambons ───
        $this->info('▶ STEP 2: เติม amphurs ที่ยังไม่มีพิกัด (centroid จาก tambons)');
        $amphursMissing = Amphur::whereNull('lat')->orWhereNull('lng')->get();
        $aFixed = 0;
        foreach ($amphursMissing as $a) {
            $c = Tambon::where('amphur_id', $a->id)
                ->whereNotNull('lat')->whereNotNull('lng')
                ->selectRaw('AVG(lat) as lat, AVG(lng) as lng')
                ->first();
            if ($c && $c->lat) {
                $this->line(sprintf('  ↺ อ.%s → [%.5f, %.5f] (centroid จาก tambons)',
                    $a->name, $c->lat, $c->lng));
                if (!$dry) $a->update(['lat' => $c->lat, 'lng' => $c->lng]);
                $aFixed++;
            }
        }
        $this->line("  ✓ amphurs เติม centroid: $aFixed");

        // ─────────── STEP 3: villages ที่อยู่ผิดที่ ───────────
        $this->info('▶ STEP 3: หาหมู่บ้านที่ห่าง tambon center > 5km แล้วคำนวณใหม่');
        $misplaced = [];
        Village::with('tambon')->chunk(500, function ($chunk) use (&$misplaced) {
            foreach ($chunk as $v) {
                if (!$v->tambon?->lat || !$v->tambon?->lng) continue;
                if (!$v->lat || !$v->lng) { $misplaced[] = $v; continue; }
                if (abs($v->lat - $v->tambon->lat) > 0.05 || abs($v->lng - $v->tambon->lng) > 0.05) {
                    $misplaced[] = $v;
                }
            }
        });

        if (empty($misplaced)) {
            $this->line('  ✓ ไม่มีหมู่บ้านผิดที่');
        } else {
            $byAmphur = [];
            foreach ($misplaced as $v) {
                $key = $v->tambon?->amphur?->name ?? '?';
                $byAmphur[$key] = ($byAmphur[$key] ?? 0) + 1;
            }
            foreach ($byAmphur as $name => $cnt) {
                $this->warn(sprintf('  อ.%s: %d หมู่บ้าน', $name, $cnt));
            }

            $bar = $this->output->createProgressBar(count($misplaced));
            $bar->start();
            foreach ($misplaced as $v) {
                if (!$dry) $v->ensureCoords(true);
                $bar->advance();
            }
            $bar->finish();
            $this->newLine();
            $this->line(sprintf('  ✓ แก้พิกัด villages: %d', count($misplaced)));
        }

        $this->newLine();
        if ($dry) {
            $this->warn('DRY RUN — ไม่ได้บันทึกอะไรเลย ลองอีกครั้งโดยไม่ใส่ --dry');
        } else {
            $this->info('✅ เสร็จสมบูรณ์ — กรุณา reload หน้าแผนที่เพื่อดูผล');
        }
        return self::SUCCESS;
    }
}

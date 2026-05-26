<?php

namespace App\Console\Commands;

use App\Models\Village;
use Illuminate\Console\Command;

class BackfillVillageCoords extends Command
{
    protected $signature = 'villages:backfill-coords {--dry : แสดงรายการที่จะแก้ไขโดยไม่บันทึก}';
    protected $description = 'เติม lat/lng ให้หมู่บ้านที่ยังไม่มีพิกัด ใช้ centroid ของ tambon/amphur/จังหวัด';

    public function handle(): int
    {
        $rows = Village::where(fn ($q) => $q->whereNull('lat')->orWhereNull('lng'))->with('tambon.amphur')->get();

        if ($rows->isEmpty()) {
            $this->info('✓ ทุกหมู่บ้านมีพิกัดครบแล้ว');
            return self::SUCCESS;
        }

        $this->info("พบ {$rows->count()} หมู่บ้านที่ยังไม่มีพิกัด");
        $dry = (bool) $this->option('dry');

        foreach ($rows as $v) {
            $coords = Village::resolveCenterCoords($v->tambon_id);
            $where  = sprintf('ต.%s · อ.%s', $v->tambon?->name ?? '?', $v->tambon?->amphur?->name ?? '?');
            $this->line(sprintf('  #%-5d ม.%-3s %-30s %s  →  [%.5f, %.5f]',
                $v->id, $v->moo, mb_strimwidth($v->name, 0, 28, '…'), $where, $coords['lat'], $coords['lng']));
            if (!$dry) $v->ensureCoords();
        }

        $this->newLine();
        $this->info($dry ? 'DRY RUN — ไม่ได้บันทึก ลองอีกครั้งโดยไม่ใส่ --dry' : '✓ บันทึกเรียบร้อย');
        return self::SUCCESS;
    }
}

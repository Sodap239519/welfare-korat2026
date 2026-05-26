<?php

namespace App\Console\Commands;

use App\Models\Village;
use Illuminate\Console\Command;

class BackfillVillageCoords extends Command
{
    protected $signature = 'villages:backfill-coords
        {--dry : แสดงรายการที่จะแก้ไขโดยไม่บันทึก}
        {--force : คำนวณใหม่ทุกหมู่บ้านที่ระบุ (แม้มีพิกัดอยู่)}
        {--tambon=* : เฉพาะหมู่บ้านใน tambon_id ที่ระบุ (ใส่ได้หลายตัว)}';
    protected $description = 'เติม lat/lng ให้หมู่บ้าน ใช้ tambon/amphur center จริง > centroid > province';

    public function handle(): int
    {
        $force   = (bool) $this->option('force');
        $tambons = (array) $this->option('tambon');

        $q = Village::query()->with('tambon.amphur');
        if (!empty($tambons))    $q->whereIn('tambon_id', $tambons);
        if (!$force)             $q->where(fn ($x) => $x->whereNull('lat')->orWhereNull('lng'));

        $rows = $q->get();
        if ($rows->isEmpty()) {
            $this->info('✓ ไม่มีหมู่บ้านต้องเติมพิกัด');
            return self::SUCCESS;
        }

        $this->info(sprintf('พบ %d หมู่บ้านที่จะ %s', $rows->count(), $force ? 'คำนวณพิกัดใหม่' : 'เติมพิกัด'));
        $dry = (bool) $this->option('dry');

        foreach ($rows as $v) {
            $coords = Village::resolveCenterCoords($v->tambon_id);
            $where  = sprintf('ต.%s · อ.%s', $v->tambon?->name ?? '?', $v->tambon?->amphur?->name ?? '?');
            $this->line(sprintf('  #%-5d ม.%-3s %-30s %s  →  [%.5f, %.5f] (%s)',
                $v->id, $v->moo, mb_strimwidth($v->name, 0, 28, '…'), $where,
                $coords['lat'], $coords['lng'], $coords['source']));
            if (!$dry) $v->ensureCoords($force);
        }

        $this->newLine();
        $this->info($dry ? 'DRY RUN — ไม่ได้บันทึก ลองอีกครั้งโดยไม่ใส่ --dry' : '✓ บันทึกเรียบร้อย');
        return self::SUCCESS;
    }
}

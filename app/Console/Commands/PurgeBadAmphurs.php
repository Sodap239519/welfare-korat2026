<?php

namespace App\Console\Commands;

use App\Models\Amphur;
use App\Models\Household;
use App\Models\Target;
use App\Models\Tambon;
use App\Models\Village;
use App\Support\AmphurCoords;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * ล้าง "อำเภอผี" ที่เกิดจากการ import ผิดคอลัมน์
 * (เช่น อยู่ยาก / อยู่ลำบาก / อยู่พอได้ / ไม่มีข้อมูล — ค่าจริงคือคอลัมน์ "สถานะ")
 *
 * ตรวจหาโดยเทียบกับรายชื่อ 32 อำเภอจริง — อะไรที่ไม่อยู่ในลิสต์ = ผี
 *
 *   php artisan targets:purge-bad-amphurs          # ดูเฉย ๆ (dry-run)
 *   php artisan targets:purge-bad-amphurs --force  # ลบจริง
 */
class PurgeBadAmphurs extends Command
{
    protected $signature = 'targets:purge-bad-amphurs {--force : ลบจริง (ไม่ใส่ = แสดงรายการเฉย ๆ)}';

    protected $description = 'ล้างอำเภอที่สร้างผิดจากการ import (ชื่อไม่ตรง 32 อำเภอจริง) พร้อมข้อมูลที่ผูกอยู่';

    public function handle(): int
    {
        $valid = array_keys(AmphurCoords::COORDS);

        $bad = Amphur::whereNotIn('name', $valid)->get();

        if ($bad->isEmpty()) {
            $this->info('✅ ไม่พบอำเภอผิดปกติ — อำเภอทั้งหมดอยู่ในรายชื่อ 32 อำเภอจริง');
            return self::SUCCESS;
        }

        $this->warn('พบ '.$bad->count().' อำเภอที่ไม่อยู่ในรายชื่อ 32 อำเภอจริง:');

        $rows = [];
        $totalTargets = 0;
        foreach ($bad as $a) {
            $tambonIds    = Tambon::where('amphur_id', $a->id)->pluck('id');
            $villageIds   = Village::whereIn('tambon_id', $tambonIds)->pluck('id');
            $householdIds = Household::whereIn('village_id', $villageIds)->pluck('id');
            $targetCount  = Target::where('amphur_id', $a->id)->count();
            $totalTargets += $targetCount;

            $rows[] = [
                $a->id,
                $a->name,
                $targetCount,
                $householdIds->count(),
                $villageIds->count(),
                $tambonIds->count(),
            ];
        }

        $this->table(
            ['amphur_id', 'ชื่อ (ผิด)', 'เป้าหมาย', 'ครัวเรือน', 'หมู่บ้าน', 'ตำบล'],
            $rows
        );
        $this->line("รวมเป้าหมายที่จะถูกลบ: <fg=yellow>{$totalTargets}</> รายการ");

        if (!$this->option('force')) {
            $this->newLine();
            $this->comment('นี่คือโหมดดูอย่างเดียว — ใส่ --force เพื่อลบจริง');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn('กำลังลบ... (bottom-up ใน transaction)');

        DB::transaction(function () use ($bad) {
            foreach ($bad as $a) {
                $tambonIds  = Tambon::where('amphur_id', $a->id)->pluck('id');
                $villageIds = Village::whereIn('tambon_id', $tambonIds)->pluck('id');

                // 1) targets (cascade → target_current_status, document_batch_targets)
                Target::where('amphur_id', $a->id)->delete();
                // 2) households ใต้หมู่บ้านเหล่านี้
                Household::whereIn('village_id', $villageIds)->delete();
                // 3) villages → 4) tambons → 5) amphur
                Village::whereIn('tambon_id', $tambonIds)->delete();
                Tambon::where('amphur_id', $a->id)->delete();
                $a->delete();
            }
        });

        $this->info('✅ ล้างอำเภอผิดเรียบร้อย — นำเข้าไฟล์ DSS ที่ถูกต้องใหม่ได้เลย');
        return self::SUCCESS;
    }
}

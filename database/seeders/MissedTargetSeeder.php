<?php

namespace Database\Seeders;

use App\Services\MissedTargetImportService;
use Illuminate\Database\Seeder;

class MissedTargetSeeder extends Seeder
{
    public function run(): void
    {
        $file = base_path('Data/จำนวนกลุ่มเป้าหมายผู้ตกหล่น (นครราชสีมา).xlsx');
        if (!is_file($file)) {
            $this->command->warn("ไม่พบไฟล์: $file — ข้าม MissedTargetSeeder");
            return;
        }

        $service = app(MissedTargetImportService::class);
        $result = $service->import($file, 'จำนวนกลุ่มเป้าหมายผู้ตกหล่น (นครราชสีมา).xlsx', null, 'นำเข้าเริ่มต้นจาก seeder');

        $this->command->info("MissedTargetSeeder: นำเข้า {$result['row_count']} แถว ({$result['level']}) · รวม ".number_format($result['total']).' คน');
    }
}

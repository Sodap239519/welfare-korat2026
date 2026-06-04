<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // reference data
            RoleSeeder::class,
            ChannelSeeder::class,
            RegistrationStatusSeeder::class,
            RegistrationSubStatusSeeder::class,
            ProjectPhaseSeeder::class,
            KoratGeographySeeder::class,
            // accounts จริง (2 super + 32 admin + 160 bank_staff)
            ProductionSetupSeeder::class,
            // ข้อมูลกลุ่มเป้าหมายผู้ตกหล่น
            MissedTargetSeeder::class,
            // ── demo seeders (รันเองเมื่อต้องการ dataset ทดลอง) ──
            // DemoUserSeeder · BankStaffUserSeeder · DistrictAdminUserSeeder
            // TargetImportSeeder · DemoTrackerSeeder · DemoStatusSeeder
        ]);
    }
}

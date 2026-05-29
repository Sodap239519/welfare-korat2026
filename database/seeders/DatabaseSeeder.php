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
            // accounts
            DemoUserSeeder::class,
            BankStaffUserSeeder::class,
            // bulk data
            TargetImportSeeder::class,
            // demo (remove or guard for production)
            DemoTrackerSeeder::class,
            DemoStatusSeeder::class,
        ]);
    }
}

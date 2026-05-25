<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        // Username = เบอร์โทรศัพท์, password >= 6 chars
        // Super Admin
        $super = User::updateOrCreate(
            ['phone' => '0900000001'],
            [
                'name'           => 'Super Admin',
                'email'          => 'super@welfare.korat.local',
                'password'       => Hash::make('123456'),
                'active'         => true,
            ]
        );
        $super->syncRoles(['super_admin']);

        // Admin ตำบลปากช่อง
        $admin = User::updateOrCreate(
            ['phone' => '0900000002'],
            [
                'name'           => 'นายอำเภอ ปากช่อง (Demo)',
                'email'          => 'admin.pakchong@welfare.korat.local',
                'password'       => Hash::make('123456'),
                'position_type'  => 'อื่นๆ',
                'position_other' => 'นายอำเภอ',
                'active'         => true,
            ]
        );
        $admin->syncRoles(['admin']);

        // Tracker หมู่บ้าน
        $tracker = User::updateOrCreate(
            ['phone' => '0812345678'],
            [
                'name'           => 'นายสมชาย ผู้ดูแล (Demo)',
                'email'          => 'tracker.demo@welfare.korat.local',
                'password'       => Hash::make('123456'),
                'position_type'  => 'ผู้ใหญ่บ้าน',
                'active'         => true,
            ]
        );
        $tracker->syncRoles(['tracker']);
    }
}

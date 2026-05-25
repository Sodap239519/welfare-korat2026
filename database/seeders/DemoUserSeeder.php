<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        // Super Admin
        $super = User::updateOrCreate(
            ['email' => 'super@welfare.korat.local'],
            [
                'name'           => 'Super Admin',
                'password'       => Hash::make('password'),
                'phone'          => null,
                'position_type'  => null,
                'active'         => true,
            ]
        );
        $super->syncRoles(['super_admin']);

        // Admin ตำบลปากช่อง
        $admin = User::updateOrCreate(
            ['email' => 'admin.pakchong@welfare.korat.local'],
            [
                'name'           => 'นายอำเภอ ปากช่อง (Demo)',
                'password'       => Hash::make('password'),
                'phone'          => '044-313-XXX',
                'position_type'  => 'อื่นๆ',
                'position_other' => 'นายอำเภอ',
                'active'         => true,
            ]
        );
        $admin->syncRoles(['admin']);

        // Tracker หมู่บ้าน
        $tracker = User::updateOrCreate(
            ['email' => 'tracker.demo@welfare.korat.local'],
            [
                'name'           => 'นายสมชาย ผู้ดูแล (Demo)',
                'password'       => Hash::make('password'),
                'phone'          => '081-234-5678',
                'position_type'  => 'ผู้ใหญ่บ้าน',
                'active'         => true,
            ]
        );
        $tracker->syncRoles(['tracker']);
    }
}

<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * บัญชีผู้บริหาร (executive) — เข้ามาดูรายงานนักศึกษาในมุมมองผู้บริหาร (read-only)
 * รันซ้ำได้ (firstOrCreate) — เปลี่ยนรหัสผ่านได้ภายหลังที่หน้าโปรไฟล์
 */
class ExecutiveUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['phone' => '0810000000'],
            [
                'name'     => 'ผู้บริหาร (Executive)',
                'password' => Hash::make('Exec@2569'),
                'active'   => true,
            ]
        );

        if (!$user->hasRole('executive')) {
            $user->assignRole('executive');
        }
    }
}

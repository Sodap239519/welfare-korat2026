<?php

namespace Database\Seeders;

use App\Models\Channel;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * สร้างบัญชี bank_staff อย่างละ 1 ของ 5 ธนาคาร
 *
 *   เบอร์ติดต่อ (= username เข้าระบบ)        password: 123456
 *   ─────────────────────────────────────────────────────
 *   ธ.กรุงไทย (KTB)        0911000001
 *   ธ.ออมสิน (GSB)         0911000002
 *   ธ.ก.ส. (BAAC)          0911000003
 *   ธ.อาคารสงเคราะห์ (GHB) 0911000004
 *   ธ.อิสลาม (IBANK)       0911000005
 *
 * ทุกบัญชีจะถูก scope ให้เห็นแค่ batch ของธนาคารตัวเอง
 */
class BankStaffUserSeeder extends Seeder
{
    public function run(): void
    {
        $bankCh = Channel::where('code', 'bank')->first();
        if (!$bankCh) {
            $this->command->warn('  [SKIP] ไม่พบ channel "bank" — ข้ามการ seed');
            return;
        }

        $accounts = [
            ['code' => 'KTB',   'bank' => 'ธ.กรุงไทย',           'phone' => '0911000001', 'full_name' => 'นางสาวสมหญิง รักษ์ดี'],
            ['code' => 'GSB',   'bank' => 'ธ.ออมสิน',            'phone' => '0911000002', 'full_name' => 'นายวินัย ออมเงิน'],
            ['code' => 'BAAC',  'bank' => 'ธ.ก.ส.',              'phone' => '0911000003', 'full_name' => 'นางสุดา เกษตรชัย'],
            ['code' => 'GHB',   'bank' => 'ธ.อาคารสงเคราะห์',    'phone' => '0911000004', 'full_name' => 'นายธีรพงศ์ บ้านมั่นคง'],
            ['code' => 'IBANK', 'bank' => 'ธ.อิสลามแห่งประเทศไทย','phone' => '0911000005', 'full_name' => 'นายอามีน อัล-มันชูร'],
        ];

        foreach ($accounts as $a) {
            $user = User::updateOrCreate(
                ['phone' => $a['phone']],
                [
                    // เก็บ "ชื่อ-นามสกุลจริง" ของเจ้าหน้าที่ — ไม่ใช้ชื่อธนาคาร
                    'name'             => $a['full_name'],
                    'email'            => 'bank.' . strtolower($a['code']) . '@welfare.korat.local',
                    'password'         => Hash::make('123456'),
                    // bank_staff ไม่มี ตำแหน่ง (ไม่ใช่กำนัน/ผู้ใหญ่บ้าน)
                    'position_type'    => null,
                    'position_other'   => null,
                    'bank_channel_id'  => $bankCh->id,
                    'bank_sub_channel' => strtolower($a['code']),
                    'active'           => true,
                ]
            );
            $user->syncRoles(['bank_staff']);

            $this->command->line(
                "  ✓ {$a['full_name']}  ({$a['bank']})  phone: {$a['phone']}  password: 123456  scope: {$a['code']}"
            );
        }
    }
}

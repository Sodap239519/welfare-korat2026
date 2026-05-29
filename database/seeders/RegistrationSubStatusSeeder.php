<?php

namespace Database\Seeders;

use App\Models\RegistrationSubStatus;
use Illuminate\Database\Seeder;

/**
 * Sub-status default — ขั้นย่อยของ 4.2 (กำลังลงทะเบียน)
 *
 *   4.2.1 ลงทะเบียนด้วยตนเอง          actor: tracker (mark only)
 *   4.2.2 ส่งแบบฟอร์มแล้ว             actor: tracker (เริ่มกระบวนการ batch)
 *   4.2.3 ธนาคารรับเอกสารแล้ว          actor: bank   (รับ batch)
 *   4.2.4 ธนาคารบันทึกข้อมูลแล้ว        actor: bank   (ปิด batch → 4.3+)
 */
class RegistrationSubStatusSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'code' => '4.2.1', 'parent_code' => '4.2',
                'label' => 'ลงทะเบียนด้วยตนเอง',
                'icon'  => 'fi-rr-user', 'color' => 'st-4-2',
                'sort_order' => 1, 'actor_role' => 'tracker',
            ],
            [
                'code' => '4.2.2', 'parent_code' => '4.2',
                'label' => 'ส่งแบบฟอร์มเอกสารแล้ว',
                'icon'  => 'fi-rr-paper-plane', 'color' => 'st-4-2',
                'sort_order' => 2, 'actor_role' => 'tracker',
            ],
            [
                'code' => '4.2.3', 'parent_code' => '4.2',
                'label' => 'ธนาคารรับเอกสารแล้ว',
                'icon'  => 'fi-rr-inbox-in', 'color' => 'st-4-2',
                'sort_order' => 3, 'actor_role' => 'bank',
            ],
            [
                'code' => '4.2.4', 'parent_code' => '4.2',
                'label' => 'ธนาคารบันทึกข้อมูลลงระบบแล้ว',
                'icon'  => 'fi-rr-check-double', 'color' => 'st-4-2',
                'sort_order' => 4, 'actor_role' => 'bank',
            ],
        ];

        foreach ($items as $s) {
            RegistrationSubStatus::updateOrCreate(['code' => $s['code']], $s);
        }
    }
}

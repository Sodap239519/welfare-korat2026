<?php

namespace Database\Seeders;

use App\Models\RegistrationSubStatus;
use Illuminate\Database\Seeder;

/**
 * Sub-status — 7 ขั้นย่อยของ 4.2 (กำลังลงทะเบียน) ครอบคลุม 2 path:
 *
 *  Path A (ผ่านผู้นำชุมชน/อปท. → อำเภอ → ธนาคาร) · 5 จุดยืนยัน:
 *    4.2.2 ผู้นำชุมชน/อปท. รับเอกสาร       actor: tracker
 *    4.2.3 ส่งให้อำเภอแล้ว                  actor: tracker
 *    4.2.4 อำเภอรับเอกสาร                   actor: district (admin)
 *    4.2.5 อำเภอส่งต่อให้ธนาคาร            actor: district (admin)
 *    4.2.6 ธนาคารรับเอกสาร (จากอำเภอ)      actor: bank
 *
 *  Path B (กรอกที่ธนาคารโดยตรง walk-in) · 1 จุดยืนยัน:
 *    4.2.1 ธนาคารรับเอกสาร (walk-in)        actor: bank
 *
 *  จุดจบ (ทั้ง 2 path):
 *    4.2.7 ธนาคารบันทึกข้อมูลลงระบบ         actor: bank
 */
class RegistrationSubStatusSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            // Path B (walk-in)
            [
                'code' => '4.2.1', 'parent_code' => '4.2',
                'label' => 'ธนาคารรับเอกสาร (walk-in)',
                'icon'  => 'fi-rr-bank', 'color' => 'st-4-2',
                'sort_order' => 1, 'actor_role' => 'bank',
            ],
            // Path A
            [
                'code' => '4.2.2', 'parent_code' => '4.2',
                'label' => 'ผู้นำชุมชน/อปท. รับเอกสารจากประชาชน',
                'icon'  => 'fi-rr-users-alt', 'color' => 'st-4-2',
                'sort_order' => 2, 'actor_role' => 'tracker',
            ],
            [
                'code' => '4.2.3', 'parent_code' => '4.2',
                'label' => 'ส่งให้อำเภอแล้ว',
                'icon'  => 'fi-rr-paper-plane', 'color' => 'st-4-2',
                'sort_order' => 3, 'actor_role' => 'tracker',
            ],
            [
                'code' => '4.2.4', 'parent_code' => '4.2',
                'label' => 'อำเภอรับเอกสาร',
                'icon'  => 'fi-rr-inbox-in', 'color' => 'st-4-2',
                'sort_order' => 4, 'actor_role' => 'district',
            ],
            [
                'code' => '4.2.5', 'parent_code' => '4.2',
                'label' => 'อำเภอส่งต่อให้ธนาคารแล้ว',
                'icon'  => 'fi-rr-paper-plane', 'color' => 'st-4-2',
                'sort_order' => 5, 'actor_role' => 'district',
            ],
            [
                'code' => '4.2.6', 'parent_code' => '4.2',
                'label' => 'ธนาคารรับเอกสาร (จากอำเภอ)',
                'icon'  => 'fi-rr-inbox-in', 'color' => 'st-4-2',
                'sort_order' => 6, 'actor_role' => 'bank',
            ],
            // จบทั้ง 2 path
            [
                'code' => '4.2.7', 'parent_code' => '4.2',
                'label' => 'ธนาคารบันทึกข้อมูลลงระบบครบ',
                'icon'  => 'fi-rr-check-double', 'color' => 'st-4-2',
                'sort_order' => 7, 'actor_role' => 'bank',
            ],
        ];

        foreach ($items as $s) {
            RegistrationSubStatus::updateOrCreate(['code' => $s['code']], $s);
        }
    }
}

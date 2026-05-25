<?php

namespace Database\Seeders;

use App\Models\RegistrationStatus;
use Illuminate\Database\Seeder;

class RegistrationStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['code' => '4.1', 'label' => 'ไม่ประสงค์รับบัตรสวัสดิการ',     'color' => 'st-4-1', 'requires_note' => true,  'requires_channel' => false, 'sort_order' => 1],
            ['code' => '4.2', 'label' => 'อยู่ระหว่างการลงทะเบียน',          'color' => 'st-4-2', 'requires_note' => false, 'requires_channel' => true,  'sort_order' => 2],
            ['code' => '4.3', 'label' => 'อยู่ระหว่างขั้นตอนการส่งเอกสาร',    'color' => 'st-4-3', 'requires_note' => false, 'requires_channel' => false, 'sort_order' => 3],
            ['code' => '4.4', 'label' => 'อยู่ระหว่างการส่งเอกสารเพิ่มเติม',  'color' => 'st-4-4', 'requires_note' => true,  'requires_channel' => false, 'sort_order' => 4],
            ['code' => '4.5', 'label' => 'ลงทะเบียนไม่ผ่าน รออุทธรณ์ผล',   'color' => 'st-4-5', 'requires_note' => true,  'requires_channel' => false, 'sort_order' => 5],
            ['code' => '4.6', 'label' => 'ลงทะเบียนแล้ว รอยืนยันตัวตน',    'color' => 'st-4-6', 'requires_note' => false, 'requires_channel' => false, 'sort_order' => 6],
            ['code' => '4.7', 'label' => 'ยืนยันตัวตนแล้ว รอใช้สิทธิ',       'color' => 'st-4-7', 'requires_note' => false, 'requires_channel' => false, 'sort_order' => 7],
        ];

        foreach ($statuses as $s) {
            RegistrationStatus::updateOrCreate(['code' => $s['code']], $s);
        }
    }
}

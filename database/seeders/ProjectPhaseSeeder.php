<?php

namespace Database\Seeders;

use App\Models\ProjectPhase;
use Illuminate\Database\Seeder;

class ProjectPhaseSeeder extends Seeder
{
    public function run(): void
    {
        $phases = [
            ['sop_level' => 1, 'name' => 'วิเคราะห์ + สื่อสาร',     'icon' => 'fi-rr-megaphone',   'is_current' => false, 'sort_order' => 1, 'description' => 'Briefing 1 ครั้ง + แจกรายชื่อให้เจ้าหน้าที่ลงพื้นที่'],
            ['sop_level' => 2, 'name' => 'ลงทะเบียน 4 ช่องทาง',     'icon' => 'fi-rr-edit',        'is_current' => false, 'sort_order' => 2, 'description' => 'DSS รายตำบล/หมู่/บ้าน · ลงทะเบียนผ่าน 4 ช่องทาง'],
            ['sop_level' => 3, 'name' => 'เตรียมเอกสาร NOAH',         'icon' => 'fi-rr-folder',      'is_current' => false, 'sort_order' => 3, 'description' => '10 วันหลังลงทะเบียน'],
            ['sop_level' => 4, 'name' => 'ดำเนินการ 3 ฝ่าย',           'icon' => 'fi-rr-search',      'is_current' => true,  'sort_order' => 4, 'description' => 'ตรวจสิทธิ์ / ส่งเอกสารเพิ่ม / ยืนยันตัวตน'],
            ['sop_level' => 5, 'name' => 'ติดตามผล Dashboard',         'icon' => 'fi-rr-chart-pie',   'is_current' => false, 'sort_order' => 5, 'description' => 'Onboard 61,743 คน · ส่งรายงานทุก 16:30 ผ่าน CRM'],
        ];

        foreach ($phases as $p) {
            ProjectPhase::updateOrCreate(['sop_level' => $p['sop_level']], $p);
        }
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * เพิ่ม sub_status_code ใน target_current_status + target_status_logs
 * เพิ่ม submitter_role + submitter_name ใน target_status_logs
 *
 * sub_status_code ใช้แตก 4.2 เป็น 4.2.1-4.2.4:
 *   4.2.1 ลงทะเบียนด้วยตนเอง
 *   4.2.2 ส่งแบบฟอร์มแล้ว (ผ่านกำนัน/ผู้ใหญ่บ้าน/อสม./อปท./อื่นๆ)
 *   4.2.3 ธนาคารรับเอกสารแล้ว
 *   4.2.4 ธนาคารบันทึกข้อมูลแล้ว
 *
 * submitter_role: 'self' | 'kamnan' | 'phuyaiban' | 'osm' | 'opt' | 'other'
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('target_current_status', function (Blueprint $table) {
            $table->string('sub_status_code', 10)->nullable()->after('status_code')->index();
        });

        Schema::table('target_status_logs', function (Blueprint $table) {
            $table->string('sub_status_code', 10)->nullable()->after('status_code')->index();
            $table->string('submitter_role', 20)->nullable()->after('sub_status_code');
            $table->string('submitter_name', 150)->nullable()->after('submitter_role');
        });
    }

    public function down(): void
    {
        Schema::table('target_current_status', function (Blueprint $table) {
            $table->dropColumn('sub_status_code');
        });
        Schema::table('target_status_logs', function (Blueprint $table) {
            $table->dropColumn(['sub_status_code', 'submitter_role', 'submitter_name']);
        });
    }
};

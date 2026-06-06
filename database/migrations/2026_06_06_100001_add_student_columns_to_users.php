<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * เพิ่มคอลัมน์โปรไฟล์นักศึกษา ใน users (role 'student')
 *
 * นักศึกษา มร.นม. ที่ลงพื้นที่ช่วยประชาชนลงทะเบียนบัตรสวัสดิการ
 * - student_id      → รหัสนักศึกษา
 * - faculty / major → คณะ / สาขาวิชา
 * - line_id         → ID LINE (ติดต่อ)
 * - work_unit_type  → หน่วยปฏิบัติงาน: 'amphur' (ที่ว่าการอำเภอ) | 'bank' (ธนาคาร)
 *
 * ใช้คอลัมน์เดิมซ้ำ: amphur_id (อำเภอที่ปฏิบัติงาน) · bank_channel_id + bank_branch (ธนาคาร/สาขา)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('student_id', 30)->nullable()->after('position_other');
            $table->string('faculty', 150)->nullable()->after('student_id');
            $table->string('major', 150)->nullable()->after('faculty');
            $table->string('line_id', 100)->nullable()->after('major');
            $table->string('work_unit_type', 20)->nullable()->after('line_id'); // amphur | bank
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['student_id', 'faculty', 'major', 'line_id', 'work_unit_type']);
        });
    }
};

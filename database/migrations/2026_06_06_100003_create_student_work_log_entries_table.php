<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * รายการกิจกรรมในแต่ละวัน (child ของ student_work_logs)
 * ตารางในฟอร์ม: ช่วงเวลา (เช้า/บ่าย/เพิ่มเติม) · กิจกรรมที่ดำเนินการ · รายละเอียด · จำนวนผู้รับบริการ
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('student_work_log_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_log_id')->constrained('student_work_logs')->cascadeOnDelete();
            $table->string('period', 20);          // เช้า | บ่าย | เพิ่มเติม
            $table->string('activity_type', 150);  // ให้คำแนะนำการลงทะเบียน / ช่วยกรอกข้อมูล / ตรวจสอบเอกสาร / คัดกรองกลุ่มตกหล่น / อื่นๆ
            $table->text('detail')->nullable();
            $table->unsignedInteger('service_count')->default(0);
            $table->timestamps();

            $table->index('work_log_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_work_log_entries');
    }
};

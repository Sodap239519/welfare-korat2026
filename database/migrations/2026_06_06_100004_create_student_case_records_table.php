<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ปัญหาการลงทะเบียนรายกรณี — เก็บข้อมูลรายคน สำหรับกำกับติดตาม
 * ตามฟอร์ม: ชื่อ-สกุล · เบอร์โทร · หมู่บ้าน/ตำบล · ปัญหาที่เกิดขึ้น
 *
 * work_log_id nullable — ผูกกับวันที่บันทึกได้ หรือบันทึกแยกก็ได้
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('student_case_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_log_id')->nullable()->constrained('student_work_logs')->nullOnDelete();
            $table->string('full_name', 150);
            $table->string('phone', 20)->nullable();
            $table->string('village_tambon', 200)->nullable();
            $table->text('problem');
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_case_records');
    }
};

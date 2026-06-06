<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * บันทึกการปฏิบัติงานรายวันของนักศึกษา (1 ระเบียน = 1 วัน)
 * ตาม "แบบฟอร์มการปฏิบัติงานหนุนเสริม" — ส่วนบันทึกการปฏิบัติงานรายวัน
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('student_work_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->time('time_start')->nullable();
            $table->time('time_end')->nullable();
            // ผลการดำเนินงานรายวัน
            $table->unsignedInteger('registered_success')->default(0);
            $table->unsignedInteger('registered_fail')->default(0);
            // ผู้ควบคุมงาน
            $table->string('supervisor_name', 150)->nullable();
            $table->string('supervisor_position', 100)->nullable();
            $table->date('supervisor_date')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_work_logs');
    }
};

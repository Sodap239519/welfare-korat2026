<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ไฟล์แนบของบันทึกการปฏิบัติงานนักศึกษา
 * category: worklog_doc (ใบบันทึกการปฏิบัติงาน) | reimburse_doc (เอกสารเบิกจ่าย) | photo (ภาพการปฏิบัติงาน)
 * เก็บแบบ private (disk local) — เสิร์ฟผ่าน route ตรวจสิทธิ์
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('student_work_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_log_id')->constrained('student_work_logs')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category', 20);       // worklog_doc | reimburse_doc | photo
            $table->string('original_name', 255);
            $table->string('path', 500);
            $table->string('disk', 20)->default('local');
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->timestamps();

            $table->index(['work_log_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_work_files');
    }
};

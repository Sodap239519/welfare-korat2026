<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * เพิ่มฟิลด์ "หน่วยบริการที่นักศึกษาไปปฏิบัติงาน"
 * (เช่น ที่ว่าการอำเภอ / ธนาคาร + ชื่อสาขา) — ใช้แสดงในรายงานแทนพิกัด GPS
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_work_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('student_work_logs', 'work_unit')) {
                $table->string('work_unit', 200)->nullable()->after('time_end');
            }
        });
    }

    public function down(): void
    {
        Schema::table('student_work_logs', function (Blueprint $table) {
            if (Schema::hasColumn('student_work_logs', 'work_unit')) {
                $table->dropColumn('work_unit');
            }
        });
    }
};

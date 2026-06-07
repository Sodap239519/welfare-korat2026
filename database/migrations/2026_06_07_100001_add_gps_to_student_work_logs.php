<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * เพิ่มพิกัด GPS ในบันทึกการปฏิบัติงานนักศึกษา — ยืนยันการลงพื้นที่จริง
 * บันทึกพิกัดอัตโนมัติจากอุปกรณ์ (ห้ามปักหมุดเอง) + ความแม่นยำ + เวลา + สถานะ
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('student_work_logs', function (Blueprint $table) {
            $table->decimal('lat', 10, 7)->nullable()->after('time_end');
            $table->decimal('lng', 10, 7)->nullable()->after('lat');
            $table->float('location_accuracy')->nullable()->after('lng');   // เมตร
            $table->timestamp('location_at')->nullable()->after('location_accuracy');
            $table->string('location_status', 20)->nullable()->after('location_at'); // ok | low_accuracy | none
        });
    }

    public function down(): void
    {
        Schema::table('student_work_logs', function (Blueprint $table) {
            $table->dropColumn(['lat', 'lng', 'location_accuracy', 'location_at', 'location_status']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ขยาย document_batches รองรับ flow ผ่านอำเภอ (Path A 5 จุด):
 *
 *   draft → submitted_to_district → district_received → forwarded_to_bank → bank_received → bank_recorded
 *                                                                                         ↘ rejected (ทุกจุด)
 *
 * Walk-in (Path B) ไม่ใช้ batch — bank_staff อัปเดต target sub_status ตรงๆ
 *
 * ─── สิ่งที่เปลี่ยน ───
 * 1. แปลง status จาก ENUM → VARCHAR (เพื่อใส่ค่าใหม่ได้ไม่ต้อง ALTER ENUM)
 * 2. เพิ่ม fields ขั้นอำเภอ: district_received_at, district_received_by_user_id
 * 3. เพิ่ม fields ขั้นส่งต่อ: forwarded_at, forwarded_by_user_id,
 *    forwarded_to_channel_id, forwarded_to_sub_channel
 * 4. เพิ่ม target_amphur_id (เก็บอำเภอปลายทาง — derived from targets ตอนสร้าง)
 *    → ใช้ scope ให้ admin อำเภอเห็นแต่ batch ของอำเภอตัว
 * 5. backfill: batch เก่าที่ status='submitted' → 'submitted_to_district' (มี migration data)
 */
return new class extends Migration {
    public function up(): void
    {
        // 1) ENUM → VARCHAR — ใช้ ALTER COLUMN ผ่าน raw SQL (MySQL)
        DB::statement("ALTER TABLE document_batches MODIFY status VARCHAR(30) NOT NULL DEFAULT 'draft'");

        Schema::table('document_batches', function (Blueprint $table) {
            // 2) ขั้นอำเภอรับ
            $table->timestamp('district_received_at')->nullable()->after('received_by_user_id');
            $table->foreignId('district_received_by_user_id')->nullable()->after('district_received_at')
                ->constrained('users')->nullOnDelete();

            // 3) ขั้นอำเภอส่งต่อธนาคาร
            $table->timestamp('forwarded_at')->nullable()->after('district_received_by_user_id');
            $table->foreignId('forwarded_by_user_id')->nullable()->after('forwarded_at')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('forwarded_to_channel_id')->nullable()->after('forwarded_by_user_id')
                ->constrained('channels')->nullOnDelete();
            $table->string('forwarded_to_sub_channel', 50)->nullable()->after('forwarded_to_channel_id');

            // 4) อำเภอปลายทาง — สำหรับ scope filter ของ admin อำเภอ
            $table->foreignId('target_amphur_id')->nullable()->after('sub_channel')
                ->constrained('amphurs')->nullOnDelete();
            $table->index(['target_amphur_id', 'status']);
        });

        // 5) Backfill: เปลี่ยน status เก่าเป็นค่า new flow
        //    submitted → submitted_to_district (รอ district ดำเนินการ)
        //    received  → bank_received (ธนาคารเคยรับแล้วในระบบเก่า)
        //    recorded  → bank_recorded (ธนาคารบันทึกครบในระบบเก่า)
        DB::table('document_batches')->where('status', 'submitted')->update(['status' => 'submitted_to_district']);
        DB::table('document_batches')->where('status', 'received')->update(['status' => 'bank_received']);
        DB::table('document_batches')->where('status', 'recorded')->update(['status' => 'bank_recorded']);

        // Backfill target_amphur_id จาก first target ของแต่ละ batch
        DB::statement("
            UPDATE document_batches db
            INNER JOIN (
                SELECT dbt.batch_id, MIN(t.amphur_id) AS first_amphur_id
                FROM document_batch_targets dbt
                INNER JOIN targets t ON t.id = dbt.target_id
                GROUP BY dbt.batch_id
            ) sub ON sub.batch_id = db.id
            SET db.target_amphur_id = sub.first_amphur_id
            WHERE db.target_amphur_id IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('document_batches', function (Blueprint $table) {
            $table->dropForeign(['district_received_by_user_id']);
            $table->dropForeign(['forwarded_by_user_id']);
            $table->dropForeign(['forwarded_to_channel_id']);
            $table->dropForeign(['target_amphur_id']);
            $table->dropColumn([
                'district_received_at', 'district_received_by_user_id',
                'forwarded_at', 'forwarded_by_user_id',
                'forwarded_to_channel_id', 'forwarded_to_sub_channel',
                'target_amphur_id',
            ]);
        });

        DB::statement("ALTER TABLE document_batches MODIFY status ENUM('draft','submitted','received','recorded','rejected') NOT NULL DEFAULT 'draft'");
    }
};

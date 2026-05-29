<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "กล่องเอกสาร" — กรุ๊ปเอกสารลงทะเบียนที่ tracker เอาไปธนาคารใน 1 รอบ
 *
 * Lifecycle:
 *   draft     → tracker กำลังรวบรวม (ยังไม่ส่ง)
 *   submitted → tracker ยืนยันส่งแล้ว (รอธนาคาร)
 *   received  → ธนาคารยืนยันรับเอกสารแล้ว
 *   recorded  → ธนาคารบันทึกข้อมูลครบ (= 4.2.4 — ออกจาก 4.2 ไป 4.3+)
 *   rejected  → ธนาคารปฏิเสธ (พร้อมเหตุผล)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('document_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_no', 30)->unique();              // 2026-05-29-001 (auto-gen)
            $table->foreignId('tracker_user_id')->constrained('users')->cascadeOnDelete();
            $table->date('batch_date');                            // วันที่ส่ง (อาจ != submitted_at)
            $table->foreignId('channel_id')->nullable()->constrained()->nullOnDelete(); // ธนาคาร
            $table->string('sub_channel', 50)->nullable();          // สาขา/ธนาคารย่อย (code)
            $table->enum('status', ['draft', 'submitted', 'received', 'recorded', 'rejected'])->default('draft')->index();
            $table->string('submitter_role', 20)->nullable();       // ตำแหน่ง tracker ตอนส่ง
            $table->string('submitter_name', 150)->nullable();      // ชื่อระบุเอง (ถ้า 'other')

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at')->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->text('reject_reason')->nullable();
            $table->json('photo_paths')->nullable();                // proof รูปถ่ายเอกสาร/ใบเซ็นรับ
            $table->timestamps();

            $table->index(['channel_id', 'sub_channel', 'status']);
            $table->index(['tracker_user_id', 'batch_date']);
            $table->index(['batch_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_batches');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ตารางเก็บขั้นย่อย (sub-step) ของแต่ละสถานะ
 * ตัวอย่าง: 4.2 มีลูก 4.2.1, 4.2.2, 4.2.3, 4.2.4
 *
 * - admin แก้/เพิ่ม/ลบ ได้เหมือน registration_statuses
 * - ไม่กระทบ status_code หลัก — Dashboard/Reports ยังนับเป็น "4.2 รวม" ปกติ
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('registration_sub_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();        // '4.2.1', '4.2.2', ...
            $table->string('parent_code', 10)->index();  // '4.2'
            $table->string('label', 150);
            $table->string('icon', 80)->nullable();      // fi-rr-... (UI hint)
            $table->string('color', 30)->nullable();     // pill css class
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            // bookkeeping: ใครเข้าถึง sub-step นี้ได้ — 'tracker' | 'bank' | 'admin'
            $table->string('actor_role', 20)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_sub_statuses');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * เพิ่ม bank_channel_id + bank_sub_channel ใน users
 *
 * - bank_channel_id  → FK channels (ปกติ channel = 'bank')
 * - bank_sub_channel → code ธนาคารย่อย ('baac', 'ghb', 'gsb', 'ibank', 'ktb')
 *
 * ใช้สำหรับ role 'bank_staff' → กำหนดว่าเขาดูแลธนาคารไหน
 * Batch inbox จะกรองตามนี้: เห็นแค่ batch ที่ channel_id + sub_channel ตรง
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('bank_channel_id')->nullable()->after('position_other')
                ->constrained('channels')->nullOnDelete();
            $table->string('bank_sub_channel', 50)->nullable()->after('bank_channel_id');
            $table->index(['bank_channel_id', 'bank_sub_channel']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['bank_channel_id']);
            $table->dropColumn(['bank_channel_id', 'bank_sub_channel']);
        });
    }
};

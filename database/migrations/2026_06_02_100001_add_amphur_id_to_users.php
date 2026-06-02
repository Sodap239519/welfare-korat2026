<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * เพิ่ม amphur_id ใน users — สำหรับ role 'admin' ผูกอำเภอที่ดูแล
 * (เหมือน bank_channel_id ของ bank_staff)
 *
 * Super Admin / Tracker / Bank Staff → ปล่อย null
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('amphur_id')->nullable()->after('bank_sub_channel')
                ->constrained('amphurs')->nullOnDelete();
            $table->index('amphur_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['amphur_id']);
            $table->dropColumn('amphur_id');
        });
    }
};

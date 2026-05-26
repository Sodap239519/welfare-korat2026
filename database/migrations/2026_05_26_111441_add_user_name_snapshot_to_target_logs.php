<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Snapshot ชื่อ user ตอนบันทึก log — ไม่เปลี่ยนเมื่อ user เปลี่ยนชื่อภายหลัง
        Schema::table('target_status_logs', function (Blueprint $table) {
            $table->string('user_name_snapshot', 150)->nullable()->after('user_id');
        });
        Schema::table('target_current_status', function (Blueprint $table) {
            $table->string('updated_by_name', 150)->nullable()->after('updated_by');
        });

        // Backfill ค่า existing logs ใช้ user.name ปัจจุบัน
        DB::statement('
            UPDATE target_status_logs l
            JOIN users u ON u.id = l.user_id
            SET l.user_name_snapshot = u.name
            WHERE l.user_id IS NOT NULL
        ');
        DB::statement('
            UPDATE target_current_status tcs
            JOIN users u ON u.id = tcs.updated_by
            SET tcs.updated_by_name = u.name
            WHERE tcs.updated_by IS NOT NULL
        ');
    }

    public function down(): void
    {
        Schema::table('target_status_logs', function (Blueprint $table) {
            $table->dropColumn('user_name_snapshot');
        });
        Schema::table('target_current_status', function (Blueprint $table) {
            $table->dropColumn('updated_by_name');
        });
    }
};

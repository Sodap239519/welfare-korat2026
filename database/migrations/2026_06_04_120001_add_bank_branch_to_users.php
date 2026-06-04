<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('users', 'bank_branch')) return;
        Schema::table('users', function (Blueprint $table) {
            // ชื่อสาขาธนาคาร (ไม่บังคับ) — สำหรับ bank_staff
            $table->string('bank_branch', 150)->nullable()->after('bank_sub_channel');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('bank_branch');
        });
    }
};

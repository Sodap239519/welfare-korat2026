<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('target_status_logs', function (Blueprint $table) {
            // เช่น "KTB" / "GSB" / "BAAC" / "GHB" / "IBANK" สำหรับช่องทาง bank
            $table->string('sub_channel', 20)->nullable()->after('channel_id');
            $table->index(['channel_id', 'sub_channel']);
        });

        Schema::table('target_current_status', function (Blueprint $table) {
            $table->string('sub_channel', 20)->nullable()->after('channel_id');
            $table->index('sub_channel');
        });
    }

    public function down(): void
    {
        Schema::table('target_status_logs', function (Blueprint $table) {
            $table->dropIndex(['channel_id', 'sub_channel']);
            $table->dropColumn('sub_channel');
        });
        Schema::table('target_current_status', function (Blueprint $table) {
            $table->dropIndex(['sub_channel']);
            $table->dropColumn('sub_channel');
        });
    }
};

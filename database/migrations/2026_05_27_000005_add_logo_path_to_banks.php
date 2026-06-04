<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('banks', 'logo_path')) return;  // กันรันซ้ำ (กรณีไฟล์ถูก rename)
        Schema::table('banks', function (Blueprint $table) {
            // เก็บ path/URL ของไฟล์โลโก้ที่อัปโหลด (เช่น "/img/banks/ktb-1748275811.png")
            $table->string('logo_path', 255)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('banks', function (Blueprint $table) {
            $table->dropColumn('logo_path');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('email');
            $table->string('position_type', 40)->nullable()->after('phone'); // ผู้ใหญ่บ้าน, กำนัน, อสม., ส.อบต., อื่นๆ
            $table->string('position_other', 100)->nullable()->after('position_type');
            $table->boolean('active')->default(true)->after('position_other');
            $table->timestamp('last_login_at')->nullable()->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'position_type', 'position_other', 'active', 'last_login_at']);
        });
    }
};

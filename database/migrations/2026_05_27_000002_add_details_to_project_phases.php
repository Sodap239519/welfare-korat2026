<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('project_phases', function (Blueprint $table) {
            // เก็บ summary + bullets + footer แบบ JSON เพื่อให้ Super Admin แก้ได้
            // โครงสร้าง: { summary: string, bullets: [{icon, text}], footer: string }
            $table->json('details')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('project_phases', function (Blueprint $table) {
            $table->dropColumn('details');
        });
    }
};

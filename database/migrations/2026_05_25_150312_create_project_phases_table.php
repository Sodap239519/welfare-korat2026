<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('project_phases', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);                 // เช่น "วิเคราะห์ + สื่อสาร"
            $table->unsignedTinyInteger('sop_level');    // 1..5
            $table->string('icon', 40)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_current')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('sop_level');
            $table->index('is_current');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_phases');
    }
};

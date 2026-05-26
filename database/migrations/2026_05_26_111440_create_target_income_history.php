<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ประวัติการแก้ไขรายได้ของ target (เก็บ Baseline เดิม)
        Schema::create('target_income_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('target_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('old_income')->default(0);
            $table->unsignedInteger('new_income')->default(0);
            $table->boolean('is_baseline')->default(false);  // true = ค่าเริ่มต้นจาก import (snapshot)
            $table->string('note', 500)->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('changed_by_name', 150)->nullable();   // snapshot ชื่อผู้แก้ ณ เวลาที่บันทึก
            $table->timestamp('changed_at')->useCurrent();
            $table->timestamps();

            $table->index(['target_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('target_income_history');
    }
};

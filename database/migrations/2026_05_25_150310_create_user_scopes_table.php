<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ขอบเขตพื้นที่รับผิดชอบของ user
        // Super Admin = ไม่ต้องมี row นี้ (เห็นทุกอย่าง)
        // Admin = scope = amphur หรือ tambon
        // Tracker = scope = village
        Schema::create('user_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('scope_type', ['amphur', 'tambon', 'village']);
            $table->unsignedBigInteger('scope_id');
            $table->timestamps();

            $table->index(['user_id', 'scope_type']);
            $table->index(['scope_type', 'scope_id']);
            $table->unique(['user_id', 'scope_type', 'scope_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_scopes');
    }
};

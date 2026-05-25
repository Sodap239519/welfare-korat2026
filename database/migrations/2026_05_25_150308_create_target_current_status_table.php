<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Denormalized 1:1 with targets — เพื่อ query เร็วโดยไม่ต้อง group ทุกครั้ง
        Schema::create('target_current_status', function (Blueprint $table) {
            $table->foreignId('target_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('status_code', 10)->index();
            $table->foreignId('channel_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('updated_at')->useCurrent();

            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('target_current_status');
    }
};

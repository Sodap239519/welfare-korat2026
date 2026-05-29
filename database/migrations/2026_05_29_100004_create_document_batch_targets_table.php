<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Junction table — ผูก target กับ batch (many-to-many ในเชิงทฤษฎี
 * แต่ใน practice target ใหม่ไปได้ทีละ batch เท่านั้น → unique constraint)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('document_batch_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('document_batches')->cascadeOnDelete();
            $table->foreignId('target_id')->constrained()->cascadeOnDelete();
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamps();

            $table->unique(['batch_id', 'target_id']);
            $table->index('target_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_batch_targets');
    }
};

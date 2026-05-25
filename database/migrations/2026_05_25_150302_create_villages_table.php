<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('villages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tambon_id')->constrained()->cascadeOnDelete();
            $table->string('moo', 10)->nullable();   // หมู่ที่ - บางที่เป็นชุมชน ไม่มีหมู่
            $table->string('name', 150);             // ชื่อหมู่บ้าน/ชุมชน
            $table->timestamps();

            $table->unique(['tambon_id', 'moo', 'name']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('villages');
    }
};

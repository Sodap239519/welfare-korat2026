<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tambons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('amphur_id')->constrained()->cascadeOnDelete();
            $table->string('code', 8)->nullable();
            $table->string('name', 100);
            $table->timestamps();

            $table->unique(['amphur_id', 'name']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tambons');
    }
};

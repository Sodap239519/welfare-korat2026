<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('registration_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();   // "4.1" .. "4.7"
            $table->string('label', 100);
            $table->string('color', 20)->nullable(); // pill css class
            $table->boolean('requires_note')->default(false);
            $table->boolean('requires_channel')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_statuses');
    }
};

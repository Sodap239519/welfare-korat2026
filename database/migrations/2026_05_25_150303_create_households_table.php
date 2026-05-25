<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('households', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->constrained()->cascadeOnDelete();
            // เก็บรหัสบ้านเป็น hash เพื่อ lookup โดยไม่เปิดเผยเลขจริง
            // (เลขจริงเก็บเข้ารหัสในคอลัมน์ house_code_enc)
            $table->string('house_code_hash', 64)->index();
            $table->text('house_code_enc');                 // encrypted
            $table->string('address_no', 50)->nullable();   // บ้านเลขที่ "27/1", "229/55"
            $table->timestamps();

            $table->unique('house_code_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('households');
    }
};

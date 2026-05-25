<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            // Denormalized for fast aggregate (60k+ records)
            $table->foreignId('village_id')->constrained();
            $table->foreignId('tambon_id')->constrained();
            $table->foreignId('amphur_id')->constrained();

            $table->unsignedTinyInteger('member_seq');  // ลำดับสมาชิกในบ้าน
            $table->unsignedSmallInteger('year')->nullable(); // ปี พ.ศ. ของข้อมูล เช่น 2566
            $table->string('prefix', 20)->nullable();   // นาย, นาง, นางสาว, ฯลฯ
            $table->string('first_name', 100);
            $table->string('last_name', 100)->nullable();
            $table->string('citizen_id_hash', 64)->nullable()->index(); // เผื่ออนาคต
            $table->text('citizen_id_enc')->nullable();                  // เผื่ออนาคต

            $table->string('poverty_level', 30)->nullable();  // "อยู่ยาก", "อยู่ลำบาก"
            $table->boolean('has_old_welfare')->default(false);
            $table->unsignedInteger('annual_income')->default(0);

            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['household_id', 'member_seq']);
            $table->index(['village_id', 'active']);
            $table->index(['tambon_id', 'active']);
            $table->index(['amphur_id', 'active']);
            $table->index('year');
            $table->index('first_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('targets');
    }
};

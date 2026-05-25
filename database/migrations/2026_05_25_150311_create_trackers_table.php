<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ผู้กำกับติดตามรายหมู่บ้าน (อาจมี user_id หรือไม่ก็ได้)
        Schema::create('trackers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('village_id')->constrained();
            $table->string('full_name', 150);  // ชื่อ-สกุล (เผื่อยังไม่ได้สมัครเป็น user)
            $table->string('position', 40);    // ผู้ใหญ่บ้าน / กำนัน / ผู้ช่วยผู้ใหญ่บ้าน / อสม. / ส.อบต. / อื่นๆ
            $table->string('position_other', 100)->nullable();
            $table->string('phone', 20)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['village_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trackers');
    }
};

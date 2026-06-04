<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ประวัติการนำเข้าไฟล์ Excel
        Schema::create('missed_target_imports', function (Blueprint $table) {
            $table->id();
            $table->string('filename', 255);
            $table->enum('level', ['amphur', 'tambon'])->default('amphur');
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('total_count')->default(0);   // ผลรวมคอลัมน์ "รวมทั้ง 3 กลุ่ม"
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->string('uploaded_by_name', 150)->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();
        });

        // ข้อมูลกลุ่มเป้าหมายผู้ตกหล่น (ระดับอำเภอ/ตำบล)
        Schema::create('missed_target_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->nullable()->constrained('missed_target_imports')->nullOnDelete();
            $table->enum('level', ['amphur', 'tambon'])->default('amphur');
            $table->unsignedInteger('national_rank')->nullable();   // ลำดับระดับประเทศ
            $table->string('amphur_name', 150);
            $table->string('tambon_name', 150)->nullable();
            $table->unsignedInteger('cnt_jpt')->default(0);         // ผู้ตกเกณฑ์ จปฐ. + ไม่มีบัตรฯ
            $table->unsignedInteger('cnt_vulnerable')->default(0);  // กลุ่มเปราะบาง + ไม่มีบัตรฯ
            $table->unsignedInteger('cnt_both')->default(0);        // จปฐ. + เปราะบาง + ไม่มีบัตรฯ
            $table->unsignedInteger('cnt_total')->default(0);       // รวมทั้ง 3 กลุ่ม
            $table->timestamps();

            $table->index(['level', 'amphur_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('missed_target_stats');
        Schema::dropIfExists('missed_target_imports');
    }
};

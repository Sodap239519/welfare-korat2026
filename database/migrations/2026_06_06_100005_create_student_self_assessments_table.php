<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * การประเมินตนเองของนักศึกษา (1 ระเบียน/นักศึกษา — ประเมินเมื่อทำงานเสร็จทั้งหมด)
 * ตามฟอร์มข้อ 1–5.6
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('student_self_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // 1. ระดับความเข้าใจงาน — มาก | ปานกลาง | น้อย
            $table->string('understanding_level', 20)->nullable();
            // 2. ทักษะที่ได้รับ (เลือกได้หลายข้อ)
            $table->json('skills')->nullable();
            // 3. ลักษณะกลุ่มประชาชนที่พบส่วนใหญ่ (เลือกได้หลายข้อ)
            $table->json('people_groups')->nullable();
            // 4. บทเรียนที่ได้รับ
            $table->text('lessons')->nullable();

            // 5. ข้อสังเกตการเข้าถึงเทคโนโลยีของประชาชน
            $table->string('tech_overall', 20)->nullable();   // 5.1  >=70% | 40-69% | <=39%
            $table->string('dependency', 30)->nullable();     // 5.2  ไม่ต้องช่วย | ช่วยบางส่วน | ทำแทนทั้งหมด
            $table->json('problems')->nullable();             // 5.3  ลักษณะปัญหา (หลายข้อ)
            $table->string('problems_other', 200)->nullable();
            $table->string('top_problem', 255)->nullable();   // 5.3.1 ปัญหาที่พบมากที่สุด
            $table->string('student_role', 30)->nullable();   // 5.4  อธิบายขั้นตอน | ช่วยบางส่วน | ทำแทนทั้งหมด
            $table->unsignedInteger('total_people')->nullable(); // 5.5  จำนวนประชาชนทั้งหมด
            $table->string('success_rate', 20)->nullable();   // 5.5  >80% | 50-79% | <50%
            $table->string('without_help', 30)->nullable();   // 5.6  ยังเข้าถึงได้ | จะไม่สามารถเข้าถึงได้

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_self_assessments');
    }
};

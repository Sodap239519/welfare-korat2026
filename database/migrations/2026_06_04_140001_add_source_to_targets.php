<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('targets', 'source')) return;
        Schema::table('targets', function (Blueprint $table) {
            // ที่มาของรายชื่อ — สำหรับรายงานเปรียบเทียบ (ไม่แสดงผลในหน้าจอ)
            //   import = มาจากไฟล์นำเข้า (กลุ่มเป้าหมายที่มีในระบบ)
            //   manual = เพิ่มเองภาคสนาม (กลุ่มเป้าหมายใหม่)
            $table->string('source', 20)->default('import')->after('active')->index();
        });
    }

    public function down(): void
    {
        Schema::table('targets', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};

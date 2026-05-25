<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('report_snapshots', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['daily_villages', 'weekly_bottleneck']);
            $table->date('snapshot_date');
            $table->unsignedSmallInteger('week_num')->nullable(); // ISO week (สำหรับ weekly)
            $table->unsignedInteger('total_targets')->default(0);
            $table->unsignedInteger('total_registered')->default(0);
            $table->decimal('pct_done', 5, 2)->default(0);
            $table->json('payload');         // เนื้อหารายงาน (rows / aggregated)
            $table->string('file_path', 255)->nullable(); // path ไฟล์ xlsx ที่ generate
            $table->timestamps();

            $table->index(['type', 'snapshot_date']);
            $table->unique(['type', 'snapshot_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_snapshots');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('banks', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 120);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // ย้ายข้อมูลจาก config('banks.banks') เข้า table
        $banks = config('banks.banks', []);
        $i = 0;
        foreach ($banks as $code => $name) {
            \DB::table('banks')->insertOrIgnore([
                'code'       => $code,
                'name'       => $name,
                'sort_order' => $i++,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('banks');
    }
};

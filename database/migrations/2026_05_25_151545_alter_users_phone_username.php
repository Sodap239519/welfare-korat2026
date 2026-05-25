<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1) ลบ unique เดิมบน email
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
        });

        // 2) email → nullable, phone → not null + unique
        // (ต้องใช้ doctrine/dbal หรือ raw SQL — MySQL/MariaDB ใช้ raw จะเร็วกว่า)
        DB::statement('ALTER TABLE users MODIFY email VARCHAR(255) NULL');
        DB::statement('ALTER TABLE users MODIFY phone VARCHAR(20) NOT NULL');

        Schema::table('users', function (Blueprint $table) {
            $table->unique('email');     // unique ยังคงอยู่ แต่อนุญาต NULL หลายค่า
            $table->unique('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_phone_unique');
            $table->dropUnique('users_email_unique');
        });
        DB::statement('ALTER TABLE users MODIFY phone VARCHAR(20) NULL');
        DB::statement('ALTER TABLE users MODIFY email VARCHAR(255) NOT NULL');
        Schema::table('users', function (Blueprint $table) {
            $table->unique('email');
        });
    }
};

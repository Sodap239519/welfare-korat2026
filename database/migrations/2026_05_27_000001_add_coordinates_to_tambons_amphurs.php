<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tambons', function (Blueprint $table) {
            $table->decimal('lat', 10, 7)->nullable()->after('name');
            $table->decimal('lng', 10, 7)->nullable()->after('lat');
        });

        Schema::table('amphurs', function (Blueprint $table) {
            $table->decimal('lat', 10, 7)->nullable()->after('name');
            $table->decimal('lng', 10, 7)->nullable()->after('lat');
        });
    }

    public function down(): void
    {
        Schema::table('tambons', function (Blueprint $table) {
            $table->dropColumn(['lat', 'lng']);
        });
        Schema::table('amphurs', function (Blueprint $table) {
            $table->dropColumn(['lat', 'lng']);
        });
    }
};

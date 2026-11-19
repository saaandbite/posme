<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            // null = ikuti default global di Pengaturan (Menu Builder).
            $table->boolean('show_image')->nullable()->default(null)->after('image');
            $table->boolean('show_description')->nullable()->default(null)->after('show_image');
            // Label bebas, mis. "Baru", "Best Seller", "Pedas".
            $table->string('badge', 30)->nullable()->after('show_description');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn(['show_image', 'show_description', 'badge']);
        });
    }
};

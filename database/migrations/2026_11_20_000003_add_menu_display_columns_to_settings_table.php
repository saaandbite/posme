<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            // 'grid' (2 kolom) atau 'list' — tampilan menu customer.
            $table->string('menu_layout', 10)->default('grid')->after('accept_qris');
            // Default global; toggle per item mengalahkan nilai ini.
            $table->boolean('menu_default_show_image')->default(true)->after('menu_layout');
            $table->boolean('menu_default_show_description')->default(true)->after('menu_default_show_image');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['menu_layout', 'menu_default_show_image', 'menu_default_show_description']);
        });
    }
};

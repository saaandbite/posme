<?php

use App\Models\DiningTable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dining_tables', function (Blueprint $table) {
            $table->string('token', 32)->unique()->nullable()->after('code');
        });

        // Backfill: meja yang sudah ada dapat token acak 32 karakter.
        // (Set langsung + save — token sengaja tidak fillable.)
        DiningTable::whereNull('token')->each(function (DiningTable $table) {
            $table->token = Str::random(32);
            $table->save();
        });

        // Setelah terisi semua, token wajib ada (tidak boleh kosong).
        Schema::table('dining_tables', function (Blueprint $table) {
            $table->string('token', 32)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('dining_tables', function (Blueprint $table) {
            $table->dropColumn('token');
        });
    }
};

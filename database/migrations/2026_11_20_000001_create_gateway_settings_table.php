<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gateway_settings', function (Blueprint $table) {
            $table->id();
            // 'midtrans' | 'xendit' — satu baris per provider.
            $table->string('provider')->unique();
            // Tepat satu provider boleh aktif dalam satu waktu.
            $table->boolean('is_active')->default(false);
            // false = Sandbox, true = Produksi.
            $table->boolean('is_production')->default(false);
            // Kredensial terenkripsi (cast encrypted:array di model).
            $table->text('credentials')->nullable();
            // Hasil tes koneksi terakhir (ditampilkan di dashboard admin).
            $table->timestamp('last_test_at')->nullable();
            $table->string('last_test_status')->nullable();
            $table->text('last_test_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_settings');
    }
};

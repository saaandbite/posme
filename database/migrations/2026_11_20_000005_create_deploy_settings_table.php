<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deploy_settings', function (Blueprint $table) {
            $table->id();
            // 'forge' | 'coolify' | 'cloudflare' | 'porkbun' — satu baris per provider.
            $table->string('provider')->unique();
            // Tepat satu provider deploy (forge/coolify) dan satu provider
            // domain (cloudflare/porkbun) boleh aktif dalam satu waktu.
            $table->boolean('is_active')->default(false);
            // Kredensial terenkripsi (cast encrypted:array di model).
            $table->text('credentials')->nullable();
            // Pilihan non-rahasia: server/site/app/domain yang dipilih
            // (id, nama, status, IP, repo, branch, dsb).
            $table->json('selection')->nullable();
            // Hasil tes koneksi terakhir (ditampilkan di dashboard admin).
            $table->timestamp('last_test_at')->nullable();
            $table->string('last_test_status')->nullable();
            $table->text('last_test_message')->nullable();
            // Hasil deploy terakhir (Forge site / Coolify app).
            $table->timestamp('last_deploy_at')->nullable();
            $table->string('last_deploy_status')->nullable();
            $table->text('last_deploy_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deploy_settings');
    }
};

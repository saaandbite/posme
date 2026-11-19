<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('restaurant_name')->default('Posme Cafe');
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('service_percent', 5, 2)->default(0);
            $table->boolean('accept_cash')->default(true);
            $table->boolean('accept_qris')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};

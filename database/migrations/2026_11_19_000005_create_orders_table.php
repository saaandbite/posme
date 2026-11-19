<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dining_table_id')->constrained()->restrictOnDelete();
            $table->string('order_number')->unique(); // POS-YYYYMMDD-001
            // pending -> preparing -> ready -> served | cancelled
            $table->string('status', 20)->default('pending');
            // unpaid | waiting (menunggu konfirmasi gateway) | paid
            $table->string('payment_status', 20)->default('unpaid');
            // cash | qris (manual kasir) | midtrans (gateway)
            $table->string('payment_method', 20)->nullable();
            $table->string('midtrans_payment_type', 30)->nullable();
            $table->string('snap_token', 100)->nullable();
            $table->unsignedInteger('subtotal')->default(0);
            $table->unsignedInteger('tax_amount')->default(0);
            $table->unsignedInteger('service_amount')->default(0);
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('paid_amount')->nullable();
            $table->unsignedInteger('change_amount')->nullable();
            $table->text('customer_note')->nullable();
            $table->string('cancelled_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('served_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['payment_status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

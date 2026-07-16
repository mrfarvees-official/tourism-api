<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->string('booking_reference')->index();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('currency', 10)->default('LKR');
            $table->string('payment_method', 50)->default('card');
            $table->string('payment_brand', 50)->nullable();
            $table->string('card_last4', 4)->nullable();
            $table->string('card_holder_name')->nullable();
            $table->string('status', 50)->default('paid');
            $table->string('provider_reference')->nullable();
            $table->json('payment_payload')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'booking_id']);
            $table->index(['tenant_id', 'booking_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_payments');
    }
};

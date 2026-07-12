<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('reference');
            $table->string('customer_name');
            $table->string('customer_email')->nullable();
            $table->string('package_name')->nullable();
            $table->string('package_slug')->nullable();
            $table->string('destination')->nullable();
            $table->string('destination_slug')->nullable();
            $table->string('service_name')->nullable();
            $table->string('service_slug')->nullable();
            $table->string('activity_name')->nullable();
            $table->string('activity_slug')->nullable();
            $table->date('travel_date')->nullable();
            $table->date('return_date')->nullable();
            $table->unsignedInteger('adults')->default(0);
            $table->unsignedInteger('children')->default(0);
            $table->unsignedInteger('infants')->default(0);
            $table->unsignedInteger('travelers_count')->default(0);
            $table->unsignedInteger('total_amount')->default(0);
            $table->unsignedInteger('paid_amount')->default(0);
            $table->string('currency', 10)->default('LKR');
            $table->string('booking_status', 50)->default('pending');
            $table->string('payment_status', 50)->default('unpaid');
            $table->date('payment_due_date')->nullable();
            $table->text('notes')->nullable();
            $table->string('route_summary')->nullable();
            $table->text('trip_story')->nullable();
            $table->json('trip_highlights')->nullable();
            $table->text('destination_story')->nullable();
            $table->text('package_story')->nullable();
            $table->text('service_story')->nullable();
            $table->text('activity_story')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'reference']);
            $table->index(['tenant_id', 'booking_status']);
            $table->index(['tenant_id', 'payment_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tourism_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('slug');
            $table->string('service_name');
            $table->text('description')->nullable();
            $table->string('service_type')->nullable();
            $table->string('coverage')->nullable();
            $table->string('vehicle')->nullable();
            $table->string('response_time')->nullable();
            $table->string('pricing_model')->nullable();
            $table->string('price_label')->nullable();
            $table->integer('price_value')->nullable();
            $table->text('story')->nullable();
            $table->string('image_url')->nullable();
            $table->boolean('featured')->default(false);
            $table->string('status', 50)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'featured']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tourism_services');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('slug');
            $table->string('package_name');
            $table->text('description')->nullable();
            $table->string('duration')->nullable();
            $table->string('route_summary')->nullable();
            $table->text('inclusions')->nullable();
            $table->string('best_for')->nullable();
            $table->string('pace')->nullable();
            $table->text('highlights')->nullable();
            $table->text('story')->nullable();
            $table->string('price_label')->nullable();
            $table->integer('price_value')->nullable();
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
        Schema::dropIfExists('packages');
    }
};

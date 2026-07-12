<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('slug');
            $table->string('stay_name');
            $table->text('description')->nullable();
            $table->string('stay_type')->nullable();
            $table->string('location')->nullable();
            $table->string('room_type')->nullable();
            $table->text('amenities')->nullable();
            $table->string('price_label')->nullable();
            $table->unsignedInteger('price_value')->nullable();
            $table->string('image_url')->nullable();
            $table->boolean('featured')->default(false);
            $table->string('status', 50)->default('active');
            $table->text('story')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'featured']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stays');
    }
};

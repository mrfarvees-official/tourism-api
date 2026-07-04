<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_data_children', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_data_id')->constrained('content_data')->cascadeOnDelete();
            $table->string('source_key', 120)->nullable();
            $table->string('row_key', 120)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate();
            $table->softDeletes();

            $table->index(['content_data_id', 'sort_order']);
            $table->index(['content_data_id', 'source_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_data_children');
    }
};

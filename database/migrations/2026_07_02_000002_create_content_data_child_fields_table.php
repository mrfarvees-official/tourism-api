<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_data_child_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_data_child_id')->constrained('content_data_children')->cascadeOnDelete();
            $table->string('field_key', 80);
            $table->string('source_column', 120)->nullable();
            $table->string('field_type', 20);
            $table->string('value_string', 500)->nullable();
            $table->longText('value_text')->nullable();
            $table->bigInteger('value_int')->nullable();
            $table->boolean('value_bool')->nullable();
            $table->decimal('value_decimal', 14, 4)->nullable();
            $table->foreignId('value_asset_id')->nullable()->constrained('tenant_assets')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate();
            $table->softDeletes();

            $table->unique(['content_data_child_id', 'field_key'], 'unique_child_and_field');
            $table->index(['content_data_child_id', 'source_column'], 'index_child_and_column');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_data_child_fields');
    }
};

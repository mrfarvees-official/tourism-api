<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('full_name', 255);
            $table->string('email', 255)->nullable()->index();
            $table->string('phone', 100)->nullable();
            $table->string('nationality', 150)->nullable();
            $table->string('passport_number', 100)->nullable();
            $table->string('preferred_language', 100)->nullable();
            $table->string('loyalty_tier', 50)->default('Explorer');
            $table->string('emergency_contact', 255)->nullable();
            $table->text('address')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'full_name'], 'customers_tenant_name_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};

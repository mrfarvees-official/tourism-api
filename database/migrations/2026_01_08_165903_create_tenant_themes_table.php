<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('tenant_theme', function (Blueprint $table) {
      $table->id();
      $table->foreignId('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();

      $table->string('mode_default', 20)->default('light'); // light|dark|system
      $table->json('tokens')->nullable(); // theme tokens (colors/fonts/radius etc.)
      $table->longText('custom_css')->nullable();

      $table->timestamps();
    });
  }

  public function down(): void {
    Schema::dropIfExists('tenant_theme');
  }
};

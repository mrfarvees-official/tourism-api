<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Laravel session id (file/cookie session id)
            $table->string('session_id', 120)->index();

            // Device meta
            $table->string('device_name')->nullable();
            $table->string('device_type')->nullable();
            $table->string('os')->nullable();
            $table->string('browser')->nullable();
            $table->text('user_agent')->nullable();

            $table->ipAddress('ip_first')->nullable();
            $table->ipAddress('ip_last')->nullable();

            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            // Revocation
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoke_reason')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'revoked_at', 'expires_at', 'last_seen_at']);
            $table->unique(['user_id', 'session_id']); // avoid duplicates
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_sessions');
    }
};

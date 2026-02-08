<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('current_session_id', 120)->nullable()->after('remember_token');
            $table->timestamp('current_session_set_at')->nullable()->after('current_session_id');
            $table->index('current_session_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['current_session_id']);
            $table->dropColumn(['current_session_id','current_session_set_at']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_assets', function (Blueprint $table) {
            $table->string('public_id', 255)->nullable()->after('path');
            $table->text('secure_url')->nullable()->after('public_id');
            $table->string('resource_type', 30)->default('image')->after('secure_url');
            $table->string('original_name', 255)->nullable()->after('label');
            $table->unsignedInteger('cloudinary_version')->nullable()->after('original_name');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_assets', function (Blueprint $table) {
            $table->dropColumn([
                'public_id',
                'secure_url',
                'resource_type',
                'original_name',
                'cloudinary_version',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('bookings', 'customer_id')) {
                $table->foreignId('customer_id')
                    ->nullable()
                    ->after('tenant_id')
                    ->constrained('customers')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('bookings', 'add_ons')) {
                $table->json('add_ons')->nullable()->after('trip_highlights');
            }

            if (!Schema::hasColumn('bookings', 'itinerary')) {
                $table->json('itinerary')->nullable()->after('add_ons');
            }

            if (!Schema::hasColumn('bookings', 'support_contact')) {
                $table->string('support_contact')->nullable()->after('itinerary');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (Schema::hasColumn('bookings', 'support_contact')) {
                $table->dropColumn('support_contact');
            }

            if (Schema::hasColumn('bookings', 'itinerary')) {
                $table->dropColumn('itinerary');
            }

            if (Schema::hasColumn('bookings', 'add_ons')) {
                $table->dropColumn('add_ons');
            }

            if (Schema::hasColumn('bookings', 'customer_id')) {
                $table->dropConstrainedForeignId('customer_id');
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hotels from booking systems (Hotelbeds first, more later): their destinations and hotel descriptions
 * are kept here (refreshed by an import), prices and free rooms are always asked live.
 * Apartments and villas stay in "accommodations" (entered in the app).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_destinations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('supplier_connection_id')->constrained()->cascadeOnDelete();
            $t->string('code', 20);
            $t->string('name');
            $t->string('country_code', 5)->nullable()->index();
            $t->string('country_name')->nullable();
            $t->json('zones')->nullable();
            $t->unsignedInteger('hotels_count')->default(0);
            $t->boolean('is_active')->default(false);      // offered in the website search
            $t->timestamps();
            $t->unique(['supplier_connection_id', 'code']);
        });

        Schema::create('supplier_hotels', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('supplier_connection_id')->constrained()->cascadeOnDelete();
            $t->string('code', 40);
            $t->string('name');
            $t->string('destination_code', 20)->nullable()->index();
            $t->string('destination_name')->nullable();
            $t->string('zone_name')->nullable();
            $t->string('country_code', 5)->nullable();
            $t->unsignedTinyInteger('stars')->nullable();
            $t->string('category_code', 20)->nullable();
            $t->string('accommodation_type', 30)->nullable();
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->string('address')->nullable();
            $t->string('city')->nullable();
            $t->string('postal_code', 20)->nullable();
            $t->string('phone', 60)->nullable();
            $t->string('email')->nullable();
            $t->string('web')->nullable();
            $t->text('description')->nullable();
            $t->json('images')->nullable();
            $t->json('facilities')->nullable();
            $t->json('rooms')->nullable();
            $t->json('boards')->nullable();
            $t->unsignedInteger('ranking')->default(0);
            $t->boolean('is_active')->default(true);        // false = never shown on the website
            $t->boolean('is_featured')->default(false);     // first in the results
            $t->decimal('markup_percent', 5, 2)->nullable(); // own margin for this hotel (else the connection's)
            $t->timestamps();
            $t->unique(['supplier_connection_id', 'code']);
            $t->index(['agency_id', 'destination_code', 'is_active']);
        });

        Schema::table('bookings', function (Blueprint $t) {
            $t->foreignId('supplier_connection_id')->nullable()->after('bookable_id')->constrained()->nullOnDelete();
            $t->json('supplier_data')->nullable();          // hotel, room, rate key, net price, cancellation…
            $t->string('supplier_status', 20)->nullable();  // quoted | booked | failed | cancelled
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $t) {
            $t->dropConstrainedForeignId('supplier_connection_id');
            $t->dropColumn(['supplier_data', 'supplier_status']);
        });
        Schema::dropIfExists('supplier_hotels');
        Schema::dropIfExists('supplier_destinations');
    }
};

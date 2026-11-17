<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The old Aries system (MCS eTours B2B API): its hotels/apartments and packages for linking,
 * links on tours, price sync switches and the copy of every booking sent there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_connection_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10);                 // hotel | package
            $table->string('code', 40);
            $table->string('name');
            $table->string('country')->nullable();
            $table->string('region')->nullable();
            $table->string('city')->nullable();
            $table->json('rooms')->nullable();          // [{code, category, room, content, service}]
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['supplier_connection_id', 'kind', 'code']);
        });

        Schema::table('tours', function (Blueprint $table) {
            $table->foreignId('supplier_connection_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider_code', 40)->nullable();        // package id in the old system
            $table->string('provider_room_code', 40)->nullable();   // accommodation (room) id of the package
            $table->boolean('sync_supplier_prices')->default(true);
        });

        Schema::table('accommodations', function (Blueprint $table) {
            $table->boolean('sync_supplier_prices')->default(true);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('legacy_status', 20)->nullable();       // sent | failed | not_available
            $table->string('legacy_ref', 80)->nullable();
            $table->text('legacy_error')->nullable();
            $table->timestamp('legacy_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', fn (Blueprint $t) => $t->dropColumn(['legacy_status', 'legacy_ref', 'legacy_error', 'legacy_sent_at']));
        Schema::table('accommodations', fn (Blueprint $t) => $t->dropColumn('sync_supplier_prices'));
        Schema::table('tours', function (Blueprint $t) {
            $t->dropConstrainedForeignId('supplier_connection_id');
            $t->dropColumn(['provider_code', 'provider_room_code', 'sync_supplier_prices']);
        });
        Schema::dropIfExists('supplier_products');
    }
};

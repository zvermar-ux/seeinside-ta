<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** What came from the old Aries system: terms, room prices (with the price there) and departures, with the time */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stay_terms', function (Blueprint $table) {
            $table->timestamp('supplier_synced_at')->nullable();
        });
        Schema::table('term_prices', function (Blueprint $table) {
            $table->decimal('supplier_price', 10, 2)->nullable();
            $table->timestamp('supplier_synced_at')->nullable();
        });
        Schema::table('tour_departures', function (Blueprint $table) {
            $table->decimal('supplier_price', 10, 2)->nullable();
            $table->timestamp('supplier_synced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('stay_terms', fn (Blueprint $t) => $t->dropColumn('supplier_synced_at'));
        Schema::table('term_prices', fn (Blueprint $t) => $t->dropColumn(['supplier_price', 'supplier_synced_at']));
        Schema::table('tour_departures', fn (Blueprint $t) => $t->dropColumn(['supplier_price', 'supplier_synced_at']));
    }
};

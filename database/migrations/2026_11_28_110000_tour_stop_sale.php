<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// "Stop sale": the agency closes a whole tour or single departures (full with bookings taken in the office);
// the sync with the old system does not open them again
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $t) {
            $t->boolean('stop_sale')->default(false);
        });
        Schema::table('tour_departures', function (Blueprint $t) {
            $t->boolean('stop_sale')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('tours', fn (Blueprint $t) => $t->dropColumn('stop_sale'));
        Schema::table('tour_departures', fn (Blueprint $t) => $t->dropColumn('stop_sale'));
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Several rooms of the new app on ONE room of the old Aries system (one room, prices by occupancy: 2 adults, 3 adults…):
 * each room asks the old system with its own number of persons.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accommodation_rooms', function (Blueprint $t) {
            $t->unsignedTinyInteger('provider_guests')->nullable()->after('provider_code');
        });
    }

    public function down(): void
    {
        Schema::table('accommodation_rooms', fn (Blueprint $t) => $t->dropColumn('provider_guests'));
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // room / apartment numbers of a room type: A4, A5, Б201, Nb.10/2fl …
        Schema::table('accommodation_rooms', function (Blueprint $table) {
            $table->json('unit_numbers')->nullable();
        });
        // organized transport sold with the accommodation (bus / plane, price per adult / child)
        Schema::table('accommodations', function (Blueprint $table) {
            $table->json('transport')->nullable();
        });
        // early booking in steps: 20% until 30.11, 15% until 31.12, 10% until 31.03 …
        foreach (['tours', 'accommodations', 'events'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->json('early_booking_tiers')->nullable();
            });
        }
        // phones (fixed, mobile with Viber / WhatsApp, emergency), social networks, newsletter
        Schema::table('agencies', function (Blueprint $table) {
            $table->json('contact_info')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('accommodation_rooms', fn (Blueprint $t) => $t->dropColumn('unit_numbers'));
        Schema::table('accommodations', fn (Blueprint $t) => $t->dropColumn(['transport', 'early_booking_tiers']));
        Schema::table('tours', fn (Blueprint $t) => $t->dropColumn('early_booking_tiers'));
        Schema::table('events', fn (Blueprint $t) => $t->dropColumn('early_booking_tiers'));
        Schema::table('agencies', fn (Blueprint $t) => $t->dropColumn('contact_info'));
    }
};

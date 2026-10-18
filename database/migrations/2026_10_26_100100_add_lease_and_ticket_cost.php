<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accommodations', function (Blueprint $table) {
            // contract = the agency sells rooms from an allotment / supplier; lease = the agency rents the whole property for the season; own = owned
            $table->string('ownership', 20)->default('contract')->after('type');
            $table->date('lease_from')->nullable()->after('ownership');
            $table->date('lease_to')->nullable()->after('lease_from');
        });
        Schema::table('event_tickets', function (Blueprint $table) {
            $table->decimal('cost_price', 10, 2)->nullable()->after('price');   // price from the organizer (what the agency pays)
        });
    }

    public function down(): void
    {
        Schema::table('accommodations', fn (Blueprint $t) => $t->dropColumn(['ownership', 'lease_from', 'lease_to']));
        Schema::table('event_tickets', fn (Blueprint $t) => $t->dropColumn('cost_price'));
    }
};

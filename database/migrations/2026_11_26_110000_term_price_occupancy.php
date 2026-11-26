<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// prices of one room by the people in it, like the price list: {"2": 599, "2+1": 599, "2+2": 769}
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('term_prices', function (Blueprint $t) {
            $t->json('occupancy_prices')->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('term_prices', function (Blueprint $t) {
            $t->dropColumn('occupancy_prices');
        });
    }
};

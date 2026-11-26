<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// "Paste price list": which room / how many people each column of the agency's price list is – remembered
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accommodations', function (Blueprint $t) {
            $t->json('price_list_map')->nullable()->after('manual_prices');
        });
    }

    public function down(): void
    {
        Schema::table('accommodations', function (Blueprint $t) {
            $t->dropColumn('price_list_map');
        });
    }
};

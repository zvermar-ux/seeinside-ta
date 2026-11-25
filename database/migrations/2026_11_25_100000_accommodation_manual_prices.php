<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// prices from the agency's price list (typed / pasted here); the old Aries system then only gives terms and free places
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accommodations', function (Blueprint $t) {
            $t->boolean('manual_prices')->default(false)->after('sync_supplier_prices');
        });
    }

    public function down(): void
    {
        Schema::table('accommodations', function (Blueprint $t) {
            $t->dropColumn('manual_prices');
        });
    }
};

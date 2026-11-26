<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// a price typed / pasted by the agency: the sync with the old system never changes it
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('term_prices', function (Blueprint $t) {
            $t->boolean('price_manual')->default(false)->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('term_prices', function (Blueprint $t) {
            $t->dropColumn('price_manual');
        });
    }
};

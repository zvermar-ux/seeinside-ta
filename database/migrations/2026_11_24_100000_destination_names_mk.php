<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Macedonian (Cyrillic) names of the hotel destinations and their areas for the website: "Lefkada" → "Лефкада"
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_destinations', function (Blueprint $t) {
            $t->string('name_mk')->nullable()->after('name');
            $t->json('zones_mk')->nullable()->after('zones');   // {"NIKIANA": "Никиана"}
        });
    }

    public function down(): void
    {
        Schema::table('supplier_destinations', function (Blueprint $t) {
            $t->dropColumn(['name_mk', 'zones_mk']);
        });
    }
};

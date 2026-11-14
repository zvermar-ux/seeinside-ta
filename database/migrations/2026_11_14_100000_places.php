<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** "Place" (Сандански, Лефкада › Никиана) after Group → Destination on tours and accommodations */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['tours', 'accommodations'] as $table) {
            if (! Schema::hasColumn($table, 'place_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->foreignId('place_id')->nullable()->after('destination_id')->constrained('groups')->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['tours', 'accommodations'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropConstrainedForeignId('place_id');
            });
        }
    }
};

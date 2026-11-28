<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// "First minute" page of the website: the apartments (and tours) marked for it
return new class extends Migration
{
    public function up(): void
    {
        foreach (['accommodations', 'tours'] as $table) {
            if (! Schema::hasColumn($table, 'show_first_minute')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->boolean('show_first_minute')->default(false);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['accommodations', 'tours'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('show_first_minute'));
        }
    }
};

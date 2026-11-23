<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** General terms of travel: link of the agency, and on every booking when and which terms the client accepted */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $t) {
            $t->string('terms_url', 500)->nullable()->after('website');
        });
        Schema::table('bookings', function (Blueprint $t) {
            $t->timestamp('terms_accepted_at')->nullable();
            $t->string('terms_url', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('agencies', fn (Blueprint $t) => $t->dropColumn('terms_url'));
        Schema::table('bookings', fn (Blueprint $t) => $t->dropColumn(['terms_accepted_at', 'terms_url']));
    }
};

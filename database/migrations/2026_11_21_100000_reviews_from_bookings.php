<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Reviews from the website are written with a booking number: the review knows the booking and the exact offer */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $t) {
            $t->foreignId('booking_id')->nullable()->after('reviewable_id')->constrained()->nullOnDelete();
            $t->foreignId('visa_application_id')->nullable()->after('booking_id')->constrained()->nullOnDelete();
            $t->string('item_label', 255)->nullable()->after('visa_application_id');   // hotels from booking systems (no own record)
            $t->boolean('verified')->default(false)->after('item_label');               // the booking number + e-mail matched
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $t) {
            $t->dropConstrainedForeignId('booking_id');
            $t->dropConstrainedForeignId('visa_application_id');
            $t->dropColumn(['item_label', 'verified']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Online card payments (Halk Bank / NestPay 3D Pay Hosting) and the payment rules of the offers */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('online_payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->morphs('payable');                                   // Booking or VisaApplication
            $t->string('token', 64)->unique();                       // the link to the payment page
            $t->string('oid', 64)->unique();                         // order id sent to the bank (one per attempt)
            $t->string('provider', 20)->default('halk');
            $t->decimal('amount', 10, 2);
            $t->string('currency', 3)->default('MKD');
            $t->decimal('booking_amount', 10, 2)->nullable();        // the same amount in the currency of the booking
            $t->string('status', 20)->default('pending')->index();   // pending | approved | declined | error
            $t->string('auth_code', 40)->nullable();
            $t->string('trans_id', 60)->nullable();
            $t->string('card_mask', 30)->nullable();
            $t->string('message')->nullable();
            $t->json('response')->nullable();                        // the answer of the bank (without the hash)
            $t->text('status_check')->nullable();                    // order status query after the payment
            $t->string('return_url', 500)->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
        });

        foreach (['tours', 'accommodations', 'events', 'visa_types', 'custom_records'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'payment_policy')) {
                Schema::table($table, fn (Blueprint $t) => $t->json('payment_policy')->nullable());
            }
        }
        Schema::table('bookings', function (Blueprint $t) {
            $t->string('payment_choice', 10)->nullable()->after('payment_status');   // card | agency
            $t->decimal('payment_due_now', 10, 2)->nullable()->after('payment_choice');
        });
        Schema::table('visa_applications', function (Blueprint $t) {
            $t->string('payment_choice', 10)->nullable()->after('payment_status');
            $t->decimal('paid_amount', 10, 2)->default(0)->after('amount');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_payments');
        foreach (['tours', 'accommodations', 'events', 'visa_types', 'custom_records'] as $table) {
            if (Schema::hasColumn($table, 'payment_policy')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn('payment_policy'));
            }
        }
        Schema::table('bookings', fn (Blueprint $t) => $t->dropColumn(['payment_choice', 'payment_due_now']));
        Schema::table('visa_applications', fn (Blueprint $t) => $t->dropColumn(['payment_choice', 'paid_amount']));
    }
};

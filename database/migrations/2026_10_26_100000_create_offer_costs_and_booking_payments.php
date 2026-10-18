<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What an offer costs the agency – several lines (rates) per tour / hotel / event / charter / visa
        Schema::create('offer_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->morphs('costable');
            $table->foreignId('tour_departure_id')->nullable()->constrained()->cascadeOnDelete();   // only this departure
            $table->foreignId('accommodation_room_id')->nullable()->constrained()->cascadeOnDelete(); // only this room type
            $table->string('category', 30)->default('other');   // transport | accommodation | guide | tickets | insurance | visa | food | commission | other
            $table->string('name');
            $table->string('supplier')->nullable();
            $table->foreignId('tour_operator_id')->nullable()->constrained()->nullOnDelete();
            $table->string('basis', 30)->default('fixed');      // fixed | per_departure | per_booking | per_person | per_adult | per_child | per_person_night | per_room | per_room_night | percent_revenue
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('quantity', 10, 2)->default(1);
            $table->string('currency', 3)->default('EUR');
            $table->date('valid_from')->nullable();             // seasonal rate: only bookings travelling in this period
            $table->date('valid_to')->nullable();
            $table->decimal('paid_to_supplier', 12, 2)->default(0);
            $table->date('due_date')->nullable();
            $table->text('note')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Every payment a client makes (instalments); bookings.paid_amount = sum of these
        Schema::create('booking_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);                   // negative = refund
            $table->date('paid_at');
            $table->string('method', 30)->nullable();          // cash | card | bank | online | other
            $table->string('reference')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['booking_id', 'paid_at']);
        });

        // amounts already entered on bookings become the first payment, so nothing is lost
        $now = now();
        DB::table('bookings')->where('paid_amount', '>', 0)->orderBy('id')->chunk(500, function ($rows) use ($now) {
            DB::table('booking_payments')->insert($rows->map(fn ($b) => [
                'agency_id' => $b->agency_id, 'booking_id' => $b->id, 'amount' => $b->paid_amount,
                'paid_at' => substr((string) ($b->confirmed_at ?? $b->updated_at ?? $now), 0, 10),
                'method' => $b->payment_method ? mb_substr($b->payment_method, 0, 30) : null,
                'note' => 'Почетна состојба (пред евиденцијата на уплати)',
                'created_at' => $now, 'updated_at' => $now,
            ])->all());
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_payments');
        Schema::dropIfExists('offer_costs');
    }
};

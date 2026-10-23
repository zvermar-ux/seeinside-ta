<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* ---------- clients: persons (passengers) and companies ---------- */
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10)->default('person');      // person | company
            $table->string('title', 10)->nullable();             // Mr | Mrs | Ms | Chd | Inf
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('company_name', 190)->nullable();
            $table->string('gender', 1)->nullable();              // M | F
            $table->date('birth_date')->nullable();
            $table->string('passport_no', 40)->nullable();
            $table->date('passport_expiry')->nullable();
            $table->string('phone', 60)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('address', 190)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('tax_number', 40)->nullable();        // company: ЕДБ
            $table->string('company_number', 40)->nullable();    // company: ЕМБС
            $table->string('bank_account', 60)->nullable();
            $table->string('contact_person', 150)->nullable();
            $table->boolean('is_problem')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['agency_id', 'type', 'last_name']);
            $table->index(['agency_id', 'company_name']);
        });

        /* ---------- bookings: contract data ---------- */
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('bookable_id')->constrained('clients')->nullOnDelete();       // holder (носител)
            $table->foreignId('company_id')->nullable()->after('client_id')->constrained('clients')->nullOnDelete();      // company that pays (invoice)
            $table->foreignId('subagent_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
            $table->decimal('commission_percent', 5, 2)->nullable()->after('subagent_id');
            $table->json('price_lines')->nullable()->after('extras');          // calculation: base / supplement / discount lines
            $table->decimal('discount_percent', 5, 2)->nullable()->after('price_lines');
            $table->decimal('exchange_rate', 10, 4)->nullable()->after('currency');
            $table->string('room_number', 80)->nullable()->after('accommodation_room_id');
            $table->foreignId('stay_term_id')->nullable()->after('room_number');
            $table->date('option_until')->nullable()->after('confirmed_at');   // reservation held until
            $table->date('payment_due')->nullable()->after('option_until');    // final payment deadline
            $table->text('contract_note')->nullable()->after('internal_notes');
            $table->string('transport', 40)->nullable()->after('departure_place_id');   // bus | flight | own | none
        });

        /* ---------- accommodation terms (fixed stays) and price matrix ---------- */
        Schema::create('stay_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accommodation_id')->constrained()->cascadeOnDelete();
            $table->date('date_from');
            $table->date('date_to');
            $table->string('label', 60)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index(['accommodation_id', 'date_from']);
        });
        Schema::create('term_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stay_term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('accommodation_room_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 10, 2)->nullable();          // sale price for the whole term
            $table->decimal('cost_price', 10, 2)->nullable();     // purchase price (what the agency pays)
            $table->boolean('stop_sale')->default(false);
            $table->timestamps();
            $table->unique(['stay_term_id', 'accommodation_room_id']);
        });
        Schema::table('accommodations', function (Blueprint $table) {
            $table->boolean('book_by_terms')->default(false)->after('lease_to');
            $table->string('term_price_type', 20)->default('per_room')->after('book_by_terms');   // per_room | per_person
            $table->json('pricing_rules')->nullable()->after('term_price_type');                  // child / extra bed discounts, single supplement
        });

        /* ---------- tours: itinerary map, extra galleries, early booking label ---------- */
        Schema::table('tours', function (Blueprint $table) {
            $table->text('route_map')->nullable()->after('map_location');
            $table->json('galleries')->nullable()->after('route_map');
        });
        foreach (['tours', 'accommodations', 'events'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->string('first_minute_label', 40)->nullable()->after('first_minute_until');
                $table->decimal('price_regular', 12, 2)->nullable()->after('price_from');   // "price from" without promotion (shown struck through)
            });
        }
        Schema::table('accommodations', function (Blueprint $table) {
            $table->json('galleries')->nullable()->after('pricing_rules');
        });

        /* ---------- invoices (proforma / invoice) ---------- */
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('type', 12)->default('invoice');       // proforma | invoice
            $table->unsignedInteger('seq');
            $table->unsignedSmallInteger('year');
            $table->string('number', 30);
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('client_name', 190);
            $table->string('client_address', 255)->nullable();
            $table->string('client_tax_number', 40)->nullable();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('proforma_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('currency', 3)->default('MKD');
            $table->decimal('exchange_rate', 10, 4)->nullable();
            $table->json('lines');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('vat_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->string('status', 15)->default('issued');      // draft | issued | paid | partial | cancelled
            $table->string('payment_method', 20)->nullable();     // bank | cash | card
            $table->text('note')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['agency_id', 'type', 'year', 'seq']);
        });

        /* ---------- incoming invoices from suppliers ---------- */
        Schema::create('supplier_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('number', 60);
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->foreignId('tour_operator_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();     // supplier as a company in the client list
            $table->string('supplier_name', 190);
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('costable');                                                      // or a whole offer (bus for a departure…)
            $table->string('currency', 3)->default('EUR');
            $table->decimal('exchange_rate', 10, 4)->nullable();
            $table->decimal('amount', 12, 2);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->string('category', 30)->default('other');
            $table->string('file')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        /* ---------- cash register: every receipt (Каса прими) and payout (Каса исплати) ---------- */
        Schema::create('cash_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('register', 60)->default('main');
            $table->string('direction', 3);                        // in | out
            $table->unsignedInteger('seq');
            $table->unsignedSmallInteger('year');
            $table->dateTime('entry_at');
            $table->decimal('amount', 12, 2);                      // always positive
            $table->string('currency', 3)->default('MKD');
            $table->decimal('exchange_rate', 10, 4)->nullable();
            $table->decimal('amount_base', 12, 2)->nullable();     // in the agency's base currency (MKD)
            $table->string('method', 20)->default('cash');         // cash | card | bank
            $table->string('category', 40)->nullable();           // arrangement | air_ticket | bus_ticket | insurance | visa | supplier | expense | other
            $table->string('party_name', 190)->nullable();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('booking_payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('purpose', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['agency_id', 'direction', 'year', 'seq']);
            $table->index(['agency_id', 'register', 'entry_at']);
        });

        /* ---------- payments: may belong to an invoice, currency of the payment ---------- */
        Schema::table('booking_payments', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->after('booking_id')->constrained()->nullOnDelete();
            $table->string('currency', 3)->nullable()->after('amount');          // when paid in another currency (e.g. MKD)
            $table->decimal('original_amount', 12, 2)->nullable()->after('currency');
            $table->decimal('exchange_rate', 10, 4)->nullable()->after('original_amount');
        });

        /* ---------- agency: document & finance settings ---------- */
        Schema::table('agencies', function (Blueprint $table) {
            $table->json('documents')->nullable()->after('booking_notify_email');
        });
    }

    public function down(): void
    {
        Schema::table('agencies', fn (Blueprint $t) => $t->dropColumn('documents'));
        Schema::table('booking_payments', function (Blueprint $t) {
            $t->dropConstrainedForeignId('invoice_id');
            $t->dropColumn(['currency', 'original_amount', 'exchange_rate']);
        });
        Schema::dropIfExists('cash_entries');
        Schema::dropIfExists('supplier_invoices');
        Schema::dropIfExists('invoices');
        Schema::table('accommodations', fn (Blueprint $t) => $t->dropColumn(['book_by_terms', 'term_price_type', 'pricing_rules', 'galleries', 'first_minute_label', 'price_regular']));
        Schema::table('tours', fn (Blueprint $t) => $t->dropColumn(['route_map', 'galleries', 'first_minute_label', 'price_regular']));
        Schema::table('events', fn (Blueprint $t) => $t->dropColumn(['first_minute_label', 'price_regular']));
        Schema::dropIfExists('term_prices');
        Schema::dropIfExists('stay_terms');
        Schema::table('bookings', function (Blueprint $t) {
            foreach (['client_id', 'company_id', 'subagent_id'] as $c) {
                $t->dropConstrainedForeignId($c);
            }
            $t->dropColumn(['commission_percent', 'price_lines', 'discount_percent', 'exchange_rate', 'room_number', 'stay_term_id', 'option_until', 'payment_due', 'contract_note', 'transport']);
        });
        Schema::dropIfExists('clients');
    }
};

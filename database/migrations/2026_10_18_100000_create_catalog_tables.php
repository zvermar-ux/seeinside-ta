<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalog for the website: organized tours, events, accommodations (hotels/apartments with rooms and rates),
 * visa services, bookings, visa applications, integrations and the WordPress API.
 *
 * Text that is shown on the website is stored as JSON {"mk": "...", "en": "..."}.
 */
return new class extends Migration
{
    /** Shared "last minute" / "first minute" columns */
    protected function promoColumns(Blueprint $table): void
    {
        $table->boolean('is_last_minute')->default(false)->index();
        $table->decimal('last_minute_price', 10, 2)->nullable();
        $table->decimal('last_minute_discount_percent', 5, 2)->nullable();
        $table->date('last_minute_until')->nullable();
        $table->decimal('first_minute_discount', 10, 2)->nullable();
        $table->string('first_minute_discount_type', 10)->default('amount');   // amount | percent
        $table->date('first_minute_until')->nullable();
    }

    /** Shared publishing / SEO columns */
    protected function publishColumns(Blueprint $table): void
    {
        $table->boolean('is_active')->default(true)->index();
        $table->boolean('is_featured')->default(false);
        $table->unsignedInteger('sort_order')->default(0);
        $table->json('seo_title')->nullable();
        $table->json('seo_description')->nullable();
    }

    public function up(): void
    {
        /* ---------- reference data ---------- */

        Schema::create('departure_places', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->json('name');
            $table->string('meeting_point')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('airlines', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('iata_code', 3)->nullable();
            $table->string('logo')->nullable();
            $table->boolean('is_low_cost')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('amenities', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('name');
            $table->string('icon')->nullable();
            $table->string('category', 30)->default('general');   // general | room | wellness | family | sport | food | transport
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        /* ---------- shared media (gallery) for every catalog item ---------- */

        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->morphs('mediable');
            $table->string('path');
            $table->json('caption')->nullable();
            $table->boolean('is_main')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        /* ---------- organized tours ---------- */

        Schema::create('tours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tour_operator_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->foreignId('destination_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->string('slug');
            $table->json('title');
            $table->json('subtitle')->nullable();
            $table->string('tour_type', 30)->default('bus')->index();
            $table->string('location')->nullable();
            $table->string('country')->nullable();
            $table->string('map_location')->nullable();
            $table->unsignedSmallInteger('duration_days')->nullable();
            $table->unsignedSmallInteger('duration_nights')->nullable();
            $table->unsignedSmallInteger('group_size_min')->nullable();
            $table->unsignedSmallInteger('group_size_max')->nullable();
            $table->json('short_description')->nullable();
            $table->json('description')->nullable();
            $table->json('highlights')->nullable();
            $table->json('agenda')->nullable();
            $table->json('included')->nullable();
            $table->json('not_included')->nullable();
            $table->json('notes')->nullable();
            $table->json('important_info')->nullable();
            $table->json('cancellation_policy')->nullable();
            $table->json('payment_terms')->nullable();
            $table->json('baggage_info')->nullable();
            // prices
            $table->string('currency', 3)->default('EUR');
            $table->decimal('price_adult', 10, 2)->nullable();
            $table->decimal('price_child', 10, 2)->nullable();
            $table->unsignedTinyInteger('child_age_from')->nullable();
            $table->unsignedTinyInteger('child_age_to')->nullable();
            $table->decimal('single_supplement', 10, 2)->nullable();
            $table->decimal('price_from', 10, 2)->nullable()->index();
            $table->decimal('deposit_percent', 5, 2)->nullable();
            // flags
            $table->boolean('aries_quality')->default(false);
            $table->boolean('is_flight')->default(false);
            $table->foreignId('airline_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('has_guide')->default(false);
            // media & links
            $table->string('cover_image')->nullable();
            $table->json('video_urls')->nullable();
            $table->json('links')->nullable();
            $table->string('program_pdf')->nullable();
            // booking
            $table->boolean('booking_enabled')->default(true);
            $table->string('booking_mode', 20)->default('request');   // request | instant
            $table->string('expert_code')->nullable();
            $this->promoColumns($table);
            $this->publishColumns($table);
            $table->timestamps();
            $table->unique(['agency_id', 'slug']);
        });

        Schema::create('tour_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('day_number')->default(1);
            $table->json('title')->nullable();
            $table->json('description')->nullable();
            $table->string('meals', 20)->nullable();         // e.g. "B,D"
            $table->string('overnight')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tour_departures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained()->cascadeOnDelete();
            $table->date('start_date')->index();
            $table->date('end_date')->nullable();
            $table->unsignedSmallInteger('seats_total')->nullable();
            $table->unsignedSmallInteger('seats_booked')->default(0);
            $table->decimal('price_adult', 10, 2)->nullable();
            $table->decimal('price_child', 10, 2)->nullable();
            $table->string('status', 20)->default('available');   // available | limited | on_request | sold_out | cancelled
            $table->boolean('is_last_minute')->default(false);
            $table->decimal('last_minute_price', 10, 2)->nullable();
            $table->string('note')->nullable();
            $table->string('expert_code')->nullable();
            $table->timestamps();
        });

        Schema::create('departure_place_tour', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained()->cascadeOnDelete();
            $table->foreignId('departure_place_id')->constrained()->cascadeOnDelete();
            $table->string('departure_time', 20)->nullable();
            $table->decimal('surcharge', 10, 2)->default(0);
            $table->unique(['tour_id', 'departure_place_id']);
        });

        Schema::create('tour_extras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained()->cascadeOnDelete();
            $table->json('name');
            $table->json('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('price_type', 20)->default('per_person');   // per_person | per_booking | per_night
            $table->string('kind', 20)->default('service');            // service | excursion | supplement | insurance
            $table->boolean('is_optional')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        /* ---------- events ---------- */

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tour_id')->nullable()->constrained()->nullOnDelete();   // organized trip to the event
            $table->foreignId('destination_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->string('slug');
            $table->json('title');
            $table->string('category', 30)->default('concert')->index();
            $table->string('performer')->nullable();
            $table->string('organizer')->nullable();
            $table->string('venue')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->string('address')->nullable();
            $table->string('map_location')->nullable();
            $table->dateTime('starts_at')->nullable()->index();
            $table->dateTime('ends_at')->nullable();
            $table->string('doors_open', 10)->nullable();
            $table->string('age_restriction', 20)->nullable();
            $table->string('status', 20)->default('scheduled');   // scheduled | postponed | cancelled | sold_out
            $table->json('short_description')->nullable();
            $table->json('description')->nullable();
            $table->json('included')->nullable();
            $table->json('not_included')->nullable();
            $table->json('notes')->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->decimal('price_from', 10, 2)->nullable();
            $table->string('cover_image')->nullable();
            $table->json('video_urls')->nullable();
            $table->string('external_ticket_url')->nullable();
            $table->boolean('booking_enabled')->default(true);
            $this->promoColumns($table);
            $this->publishColumns($table);
            $table->timestamps();
            $table->unique(['agency_id', 'slug']);
        });

        Schema::create('event_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->json('name');
            $table->json('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->unsignedInteger('quantity')->nullable();
            $table->unsignedInteger('sold')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        /* ---------- integrations (external booking systems) ---------- */

        Schema::create('supplier_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 30);       // hotelbeds | filos | bros | seayou | potw | expert | golibe | other
            $table->string('name');
            $table->string('base_url')->nullable();
            $table->string('username')->nullable();
            $table->text('credentials')->nullable();   // encrypted JSON
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamp('last_sync_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        /* ---------- accommodations ---------- */

        Schema::create('accommodations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tour_operator_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->foreignId('destination_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->foreignId('supplier_connection_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider_code')->nullable();
            $table->string('slug');
            $table->string('name');
            $table->string('type', 20)->default('hotel')->index();   // hotel | apartment | aparthotel | villa | studio | guesthouse
            $table->unsignedTinyInteger('stars')->nullable();
            $table->string('address')->nullable();
            $table->string('place')->nullable();
            $table->string('region')->nullable();
            $table->string('country')->nullable();
            $table->string('map_location')->nullable();
            $table->json('short_description')->nullable();
            $table->json('overview')->nullable();
            $table->json('house_rules')->nullable();
            $table->json('notes')->nullable();
            $table->json('cancellation_policy')->nullable();
            $table->string('check_in_time', 10)->nullable();
            $table->string('check_out_time', 10)->nullable();
            $table->unsignedInteger('distance_beach_m')->nullable();
            $table->unsignedInteger('distance_center_m')->nullable();
            $table->unsignedInteger('distance_ski_lift_m')->nullable();
            $table->decimal('distance_airport_km', 6, 1)->nullable();
            $table->json('board_types')->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->decimal('price_from', 10, 2)->nullable()->index();
            $table->string('price_list_pdf')->nullable();
            $table->string('cover_image')->nullable();
            $table->json('video_urls')->nullable();
            $table->boolean('booking_enabled')->default(true);
            $table->string('booking_mode', 20)->default('request');
            $this->promoColumns($table);
            $this->publishColumns($table);
            $table->timestamps();
            $table->unique(['agency_id', 'slug']);
        });

        Schema::create('accommodation_amenity', function (Blueprint $table) {
            $table->foreignId('accommodation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('amenity_id')->constrained()->cascadeOnDelete();
            $table->primary(['accommodation_id', 'amenity_id']);
        });

        Schema::create('accommodation_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accommodation_id')->constrained()->cascadeOnDelete();
            $table->json('name');
            $table->string('room_type', 20)->default('double');   // single | double | twin | triple | family | studio | apartment | suite
            $table->string('board', 5)->default('BB');            // RO | BB | HB | FB | AI | UAI
            $table->unsignedTinyInteger('max_adults')->default(2);
            $table->unsignedTinyInteger('max_children')->default(0);
            $table->string('beds')->nullable();
            $table->unsignedSmallInteger('size_m2')->nullable();
            $table->unsignedSmallInteger('units')->nullable();
            $table->json('description')->nullable();
            $table->json('room_amenities')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->string('price_type', 20)->default('per_room');   // per_room | per_person
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('provider_code')->nullable();
            $table->timestamps();
        });

        Schema::create('room_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accommodation_room_id')->constrained()->cascadeOnDelete();
            $table->date('date_from');
            $table->date('date_to');
            $table->decimal('price', 10, 2);
            $table->string('price_type', 20)->default('per_room');
            $table->string('board', 5)->nullable();
            $table->unsignedSmallInteger('min_nights')->default(1);
            $table->unsignedSmallInteger('release_days')->nullable();
            $table->boolean('is_last_minute')->default(false);
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index(['accommodation_room_id', 'date_from', 'date_to']);
        });

        /* ---------- visas ---------- */

        Schema::create('visa_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->string('country');
            $table->string('country_code', 2)->nullable();
            $table->json('name');
            $table->string('category', 20)->default('tourist');   // tourist | business | student | work | transit | evisa | other
            $table->json('short_description')->nullable();
            $table->json('description')->nullable();
            $table->json('requirements')->nullable();
            $table->json('processing_time')->nullable();
            $table->json('validity')->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->decimal('service_fee', 10, 2)->nullable();
            $table->decimal('consular_fee', 10, 2)->nullable();
            $table->decimal('express_fee', 10, 2)->nullable();
            $table->json('questionnaire')->nullable();         // [{key,type,label:{mk,en},required,options}]
            $table->json('required_documents')->nullable();    // [{key,label:{mk,en},required}]
            $table->boolean('online_payment')->default(false);
            $table->string('cover_image')->nullable();
            $this->promoColumns($table);
            $this->publishColumns($table);
            $table->timestamps();
            $table->unique(['agency_id', 'slug']);
        });

        Schema::create('visa_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visa_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('nationality')->nullable();
            $table->date('travel_date')->nullable();
            $table->boolean('is_express')->default(false);
            $table->json('answers')->nullable();
            $table->json('documents')->nullable();             // [{key,path,name}]
            $table->string('status', 20)->default('new')->index();
            $table->string('payment_status', 20)->default('unpaid');
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('internal_notes')->nullable();
            $table->string('source', 20)->default('web');
            $table->string('locale', 5)->default('mk');
            $table->timestamps();
        });

        /* ---------- bookings (tours, events, accommodations) ---------- */

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->nullableMorphs('bookable');
            $table->foreignId('tour_departure_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('departure_place_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('accommodation_room_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->unsignedTinyInteger('adults')->default(1);
            $table->unsignedTinyInteger('children')->default(0);
            $table->json('children_ages')->nullable();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->string('customer_name');
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('customer_city')->nullable();
            $table->json('passengers')->nullable();
            $table->json('extras')->nullable();
            $table->text('message')->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->decimal('total', 10, 2)->nullable();
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->string('status', 20)->default('inquiry')->index();         // inquiry | pending | confirmed | cancelled | completed
            $table->string('payment_status', 20)->default('unpaid');           // unpaid | deposit | paid | refunded
            $table->string('payment_method', 30)->nullable();
            $table->string('external_ref')->nullable();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('internal_notes')->nullable();
            $table->string('source', 20)->default('web');
            $table->string('locale', 5)->default('mk');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        /* ---------- WordPress / API clients + webhooks ---------- */

        Schema::create('api_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('site_url')->nullable();
            $table->string('key_prefix', 12)->index();
            $table->string('key_hash');
            $table->string('webhook_url')->nullable();
            $table->text('webhook_secret')->nullable();   // encrypted
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('last_webhook_at')->nullable();
            $table->smallInteger('last_webhook_status')->nullable();
            $table->timestamps();
        });

        Schema::create('deleted_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->nullable()->index();
            $table->string('type', 30);
            $table->unsignedBigInteger('record_id');
            $table->string('slug')->nullable();
            $table->timestamp('deleted_at')->index();
        });

        /* ---------- agency: flights widget + payment ---------- */

        Schema::table('agencies', function (Blueprint $table) {
            $table->string('flight_search_url')->nullable()->after('default_currency');
            $table->text('flight_widget_code')->nullable()->after('flight_search_url');
            $table->string('payment_provider', 30)->nullable()->after('flight_widget_code');
            $table->text('payment_settings')->nullable()->after('payment_provider');   // encrypted JSON
            $table->string('booking_notify_email')->nullable()->after('payment_settings');
        });
    }

    public function down(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->dropColumn(['flight_search_url', 'flight_widget_code', 'payment_provider', 'payment_settings', 'booking_notify_email']);
        });

        foreach ([
            'deleted_records', 'api_clients', 'bookings', 'visa_applications', 'visa_types', 'room_rates',
            'accommodation_rooms', 'accommodation_amenity', 'accommodations', 'supplier_connections',
            'event_tickets', 'events', 'tour_extras', 'departure_place_tour', 'tour_departures', 'tour_days',
            'tours', 'media', 'amenities', 'airlines', 'departure_places',
        ] as $t) {
            Schema::dropIfExists($t);
        }
    }
};

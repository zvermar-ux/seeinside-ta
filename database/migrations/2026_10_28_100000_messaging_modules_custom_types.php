<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* ---------- agency: messaging channels (encrypted), enabled modules ---------- */
        Schema::table('agencies', function (Blueprint $table) {
            $table->text('messaging')->nullable();          // encrypted json: channels, tokens, templates
            $table->json('modules')->nullable();            // null = everything enabled
        });

        /* ---------- every message to / from a client (e-mail, WhatsApp, Viber, client portal) ---------- */
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direction', 3)->default('out');     // out = agency → client, in = client → agency
            $table->string('channel', 20);                         // email, whatsapp, viber, portal
            $table->string('event', 40)->nullable();               // booking_confirmed, payment_reminder … (null = written by hand)
            $table->string('recipient')->nullable();
            $table->string('subject')->nullable();
            $table->text('body');
            $table->string('status', 20)->default('sent');         // sent, failed, received, read
            $table->text('error')->nullable();
            $table->string('provider_ref')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['agency_id', 'created_at']);
            $table->index(['booking_id', 'event']);
        });

        /* ---------- client portal: private link of a booking ---------- */
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('access_token', 64)->nullable()->unique();
            $table->timestamp('cancel_requested_at')->nullable();
        });
        foreach (\Illuminate\Support\Facades\DB::table('bookings')->whereNull('access_token')->pluck('id') as $id) {
            \Illuminate\Support\Facades\DB::table('bookings')->where('id', $id)->update(['access_token' => \Illuminate\Support\Str::random(40)]);
        }

        /* ---------- record types defined by the super admin (e.g. Transport) ---------- */
        Schema::create('record_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->json('name');                    // {mk, en} plural, e.g. "Трансфери"
            $table->json('singular')->nullable();    // {mk, en}
            $table->string('icon', 60)->default('heroicon-o-squares-2x2');
            $table->json('fields')->nullable();      // [{key, label:{mk,en}, type, options, required, show_in_list, group}]
            $table->boolean('bookable')->default(true);
            $table->string('price_label', 60)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('custom_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('record_type_id')->constrained()->cascadeOnDelete();
            $table->json('title');
            $table->string('slug');
            $table->json('short_description')->nullable();
            $table->json('description')->nullable();
            $table->string('cover_image')->nullable();
            $table->json('gallery')->nullable();
            $table->json('data')->nullable();        // values of the type's own fields
            $table->decimal('price_from', 12, 2)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->boolean('booking_enabled')->default(true);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['agency_id', 'slug']);
            $table->index(['agency_id', 'record_type_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_records');
        Schema::dropIfExists('record_types');
        Schema::table('bookings', fn (Blueprint $t) => $t->dropColumn(['access_token', 'cancel_requested_at']));
        Schema::dropIfExists('messages');
        Schema::table('agencies', fn (Blueprint $t) => $t->dropColumn(['messaging', 'modules']));
    }
};

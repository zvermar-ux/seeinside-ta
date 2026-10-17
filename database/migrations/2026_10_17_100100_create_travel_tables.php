<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_operators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_own')->default(false);   // own offer (not from an external operator)
            $table->boolean('is_active')->default(true);
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->decimal('default_markup', 6, 2)->default(0);
            $table->text('notes')->nullable();
            $table->string('external_source', 30)->nullable();
            $table->string('external_id', 50)->nullable();
            $table->timestamps();
            $table->unique(['agency_id', 'external_source', 'external_id']);
        });

        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->index();                 // group | destination
            $table->foreignId('parent_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->json('name');
            $table->json('description')->nullable();
            $table->string('image')->nullable();
            $table->string('external_url')->nullable();
            $table->boolean('use_external_url')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('show_on_homepage')->default(false);
            $table->boolean('show_in_offer')->default(false);
            $table->string('external_source', 30)->nullable();
            $table->string('external_id', 50)->nullable();
            $table->timestamps();
            $table->unique(['agency_id', 'type', 'external_source', 'external_id']);
        });

        Schema::create('travel_objects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tour_operator_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20)->index();                 // arrangement | hotel | apartment
            $table->string('name');
            $table->string('room')->nullable();
            $table->string('board')->nullable();                 // BB, HB, AI ...
            $table->unsignedTinyInteger('stars')->nullable();
            $table->string('place')->nullable();
            $table->string('region')->nullable();
            $table->string('country')->nullable();
            $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->foreignId('destination_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->boolean('is_active')->default(false);
            $table->boolean('is_featured')->default(false);      // "Предлог"
            $table->decimal('min_payment_percent', 5, 2)->default(100);
            $table->decimal('markup_percent', 6, 2)->default(0);
            $table->string('map_location')->nullable();          // "lat,lng" or address
            $table->json('description')->nullable();
            $table->json('service_description')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('external_source', 30)->nullable();
            $table->string('external_id', 50)->nullable();
            $table->string('external_service_id', 50)->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->index(['agency_id', 'type', 'is_active']);
            $table->index('country');
            $table->unique(['agency_id', 'tour_operator_id', 'type', 'external_source', 'external_id', 'external_service_id'], 'travel_objects_external_unique');
        });

        Schema::create('object_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('travel_object_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('caption')->nullable();
            $table->boolean('is_main')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('external_id', 50)->nullable();
            $table->timestamps();
        });

        Schema::create('charters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('region');
            $table->date('departure_date')->nullable();
            $table->date('return_date')->nullable();
            $table->unsignedSmallInteger('seats')->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('transport_only_price', 10, 2)->nullable();
            $table->string('departure_place')->nullable();
            $table->string('destination')->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('external_source', 30)->nullable();
            $table->string('external_id', 50)->nullable();
            $table->timestamps();
        });

        Schema::create('subagents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('company');
            $table->string('contact_person')->nullable();
            $table->string('username')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('has_access')->default(false);
            $table->boolean('no_balance_limit')->default(false);
            $table->decimal('commission_percent', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('external_source', 30)->nullable();
            $table->string('external_id', 50)->nullable();
            $table->string('external_company_id', 50)->nullable();
            $table->timestamps();
            $table->unique(['agency_id', 'external_source', 'external_id']);
        });

        Schema::create('slider_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->json('title')->nullable();
            $table->string('link')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('import_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 30);
            $table->string('status', 20)->default('running');   // running | finished | failed
            $table->json('stats')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['import_runs', 'slider_images', 'subagents', 'charters', 'object_images', 'travel_objects', 'groups', 'tour_operators'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};

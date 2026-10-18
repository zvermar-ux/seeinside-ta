<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->nullableMorphs('reviewable');          // tour / event / accommodation / visa – empty = about the agency
            $t->string('name', 120);
            $t->string('email', 150)->nullable();
            $t->string('city', 100)->nullable();
            $t->unsignedTinyInteger('rating');           // 1–5
            $t->string('title', 200)->nullable();
            $t->text('body');
            $t->text('reply')->nullable();               // agency answer, shown under the review
            $t->string('trip_date', 40)->nullable();     // "Август 2026" – free text
            $t->string('status', 20)->default('pending')->index();   // pending | approved | rejected
            $t->boolean('is_featured')->default(false);
            $t->string('source', 20)->default('web');
            $t->string('locale', 5)->default('mk');
            $t->string('ip', 45)->nullable();
            $t->string('page_url', 500)->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::table('visa_types', function (Blueprint $t) {
            $t->json('info_cards')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
        Schema::table('visa_types', fn (Blueprint $t) => $t->dropColumn('info_cards'));
    }
};

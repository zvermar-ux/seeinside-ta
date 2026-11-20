<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Travel photos of clients ("Фотоприказни"): sent from the website with a booking number, shown after approval */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photo_stories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name', 120);
            $t->string('email', 150)->nullable();
            $t->string('destination', 200)->nullable();     // where they travelled / stayed
            $t->string('trip_date', 40)->nullable();
            $t->text('story')->nullable();
            $t->json('photos')->nullable();                 // [{path, thumb, w, h}]
            $t->string('status', 20)->default('pending')->index();   // pending | approved | rejected
            $t->boolean('is_featured')->default(false);
            $t->unsignedInteger('sort_order')->default(0);
            $t->string('locale', 5)->default('mk');
            $t->string('ip', 45)->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photo_stories');
    }
};

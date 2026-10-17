<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::table('agencies', function (Blueprint $table) {
            $table->string('brand_color', 20)->nullable()->after('logo');
            $table->string('brand_logo')->nullable()->after('brand_color');
            $table->string('brand_logo_dark')->nullable()->after('brand_logo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::table('agencies', fn (Blueprint $table) => $table->dropColumn(['brand_color', 'brand_logo', 'brand_logo_dark']));
    }
};

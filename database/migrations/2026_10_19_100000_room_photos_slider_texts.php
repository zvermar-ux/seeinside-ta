<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accommodation_rooms', function (Blueprint $table) {
            $table->string('cover_image')->nullable()->after('room_amenities');
        });

        Schema::table('slider_images', function (Blueprint $table) {
            $table->json('supertitle')->nullable()->after('path');
            $table->json('subtitle')->nullable()->after('title');
            $table->json('button_label')->nullable()->after('subtitle');
            $table->boolean('open_in_new_tab')->default(false)->after('link');
        });

        Schema::table('accommodations', function (Blueprint $table) {
            $table->boolean('use_supplier_availability')->default(true)->after('provider_code');
        });
    }

    public function down(): void
    {
        Schema::table('accommodation_rooms', fn (Blueprint $table) => $table->dropColumn('cover_image'));
        Schema::table('slider_images', fn (Blueprint $table) => $table->dropColumn(['supertitle', 'subtitle', 'button_label', 'open_in_new_tab']));
        Schema::table('accommodations', fn (Blueprint $table) => $table->dropColumn('use_supplier_availability'));
    }
};

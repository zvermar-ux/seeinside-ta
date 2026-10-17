<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('slider_images', function (Blueprint $table) {
            $table->json('button2_label')->nullable()->after('open_in_new_tab');
            $table->string('link2', 500)->nullable()->after('button2_label');
        });
    }

    public function down(): void
    {
        Schema::table('slider_images', fn (Blueprint $table) => $table->dropColumn(['button2_label', 'link2']));
    }
};

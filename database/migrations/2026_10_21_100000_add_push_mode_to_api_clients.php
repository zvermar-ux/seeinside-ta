<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_clients', function (Blueprint $table) {
            // the website cannot reach the app (hosting firewall): the app sends full data and photos
            $table->boolean('push_mode')->default(false)->after('webhook_secret');
            $table->timestamp('last_full_push_at')->nullable()->after('last_webhook_status');
        });
    }

    public function down(): void
    {
        Schema::table('api_clients', fn (Blueprint $table) => $table->dropColumn(['push_mode', 'last_full_push_at']));
    }
};

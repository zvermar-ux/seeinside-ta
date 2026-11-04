<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Contacts and social networks of Aries (from their requirements, 04.11.2026) – only when not filled in yet */
return new class extends Migration
{
    public function up(): void
    {
        $info = [
            'phones' => [
                ['number' => '+389 72 228 155', 'label' => 'Телефон / Viber / WhatsApp', 'type' => 'mobile', 'apps' => ['viber', 'whatsapp']],
                ['number' => '+389 71 224 081', 'label' => 'Втор мобилен', 'type' => 'mobile', 'apps' => []],
                ['number' => '+389 47 224 155', 'label' => 'Централа (5 линии)', 'type' => 'landline', 'apps' => []],
                ['number' => '+389 47 224 156', 'label' => 'Аналогна линија', 'type' => 'landline', 'apps' => []],
                ['number' => '+389 70 269 251', 'label' => '', 'type' => 'emergency', 'apps' => []],
                ['number' => '+389 70 339 382', 'label' => '', 'type' => 'emergency', 'apps' => []],
                ['number' => '+389 70 395 253', 'label' => '', 'type' => 'emergency', 'apps' => []],
                ['number' => '+389 72 208 088', 'label' => '', 'type' => 'emergency', 'apps' => []],
            ],
            'facebook' => 'https://www.facebook.com/aries.mk/',
            'instagram' => 'https://www.instagram.com/travel_with_aries/',
            'youtube' => 'https://www.youtube.com/@ariestravelchannel410',
            'newsletter_url' => 'http://eepurl.com/cVny_P',
        ];
        DB::table('agencies')->where('name', 'like', '%Aries%')->whereNull('contact_info')
            ->update(['contact_info' => json_encode($info, JSON_UNESCAPED_UNICODE)]);
    }

    public function down(): void {}
};

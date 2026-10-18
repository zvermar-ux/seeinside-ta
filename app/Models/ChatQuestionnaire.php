<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;

/** "Help me choose" questionnaire in the live chat; the answers lead to recommendations from the active offer */
class ChatQuestionnaire extends Model
{
    use BelongsToAgency;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['title' => 'array', 'intro' => 'array', 'questions' => 'array', 'offer_types' => 'array', 'is_active' => 'boolean', 'made_by_ai' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(fn (self $q) => \App\Services\Website\WebsiteNotifier::listChanged($q->agency_id, 'chat'));
        static::deleted(fn (self $q) => \App\Services\Website\WebsiteNotifier::listChanged($q->agency_id, 'chat'));
    }

    public static function categories(): array
    {
        return [
            'winter' => __('Winter holiday'), 'summer' => __('Summer holiday'), 'spa' => __('Spa & wellness'),
            'travel' => __('Travel / tours'), 'city' => __('City break'), 'event' => __('Concerts & events'), 'other' => __('Other'),
        ];
    }

    public function label(string $field = 'title', ?string $locale = null): string
    {
        $v = $this->{$field} ?? [];
        $locale ??= app()->getLocale();

        return (string) ($v[$locale] ?? $v['mk'] ?? $v['en'] ?? '');
    }
}

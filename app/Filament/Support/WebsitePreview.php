<?php

namespace App\Filament\Support;

use App\Models\ApiClient;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;

/** "Preview" button: opens the page of the record on the agency's WordPress site */
class WebsitePreview
{
    public static function siteUrl(?int $agencyId): ?string
    {
        if (! $agencyId) {
            return null;
        }
        $c = ApiClient::withoutGlobalScopes()->where('agency_id', $agencyId)->where('is_active', true)
            ->where(fn ($q) => $q->whereNotNull('site_url')->orWhereNotNull('webhook_url'))->orderByDesc('last_used_at')->first();
        if (! $c) {
            return null;
        }
        $url = $c->site_url ?: preg_replace('~/(wp-json|index\.php).*$~', '', (string) $c->webhook_url);

        return $url ? rtrim($url, '/') : null;
    }

    /** $type: tour | event | accommodation | visa | custom */
    public static function action(string $type): Action
    {
        return Action::make('websitePreview')->label(__('Preview'))->icon('heroicon-o-eye')->color('gray')
            ->tooltip(__('Opens the page on the website (save first)'))
            ->visible(fn (Model $record) => (bool) static::siteUrl($record->agency_id))
            ->url(fn (Model $record) => static::siteUrl($record->agency_id).'/?sta_preview='.$type.'&id='.$record->getKey().'&slug='.rawurlencode((string) $record->slug).'&t='.time(), shouldOpenInNewTab: true);
    }
}

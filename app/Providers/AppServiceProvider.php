<?php

namespace App\Providers;

use App\Models\User;
use Filament\Tables\Actions as TableActions;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Table;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Translatable\Facades\Translatable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // media library: "From library" button on every image field, uploads go into the library
        \App\Filament\Support\LibraryPicker::boot();

        // Every admin table: row actions (edit, duplicate, delete…) as compact icons at the start of the row
        Table::configureUsing(function (Table $table) {
            $table->actionsPosition(ActionsPosition::BeforeColumns)
                ->striped((bool) \App\Support\Branding::get('table_striped'));
            $perPage = (int) \App\Support\Branding::get('per_page');
            if (in_array($perPage, [10, 25, 50, 100], true)) {
                $table->paginated([10, 25, 50, 100])->defaultPaginationPageOption($perPage);
            }
        });

        foreach ([TableActions\EditAction::class, TableActions\ViewAction::class, TableActions\DeleteAction::class, TableActions\ReplicateAction::class] as $action) {
            $action::configureUsing(function ($a) {
                $label = $a->getLabel();
                $a->iconButton()->tooltip($label);
            });
        }

        // Short type names in polymorphic columns (bookings, gallery)
        Relation::morphMap([
            'tour' => \App\Models\Tour::class,
            'event' => \App\Models\Event::class,
            'accommodation' => \App\Models\Accommodation::class,
            'accommodation_room' => \App\Models\AccommodationRoom::class,
            'visa_type' => \App\Models\VisaType::class,
            'charter' => \App\Models\Charter::class,
            'custom' => \App\Models\CustomRecord::class,
        ]);

        // Website API (WordPress plugin)
        // Rate limits per website (API client), not per IP: every visitor's request arrives from the site's server
        $client = fn ($request) => 'api-client:'.(app()->bound(\App\Models\ApiClient::class) ? app(\App\Models\ApiClient::class)->id : $request->ip());
        \Illuminate\Support\Facades\RateLimiter::for('website', fn ($request) => \Illuminate\Cache\RateLimiting\Limit::perMinute(1200)->by($client($request)));
        // forms straight from visitors' browsers: per visitor IP
        \Illuminate\Support\Facades\RateLimiter::for('website-visitor', fn ($request) => $request->isMethod('GET')
            ? \Illuminate\Cache\RateLimiting\Limit::perMinute(60)->by('visitor:'.$request->ip())
            : \Illuminate\Cache\RateLimiting\Limit::perMinutes(10, 10)->by('visitor-post:'.$request->ip()));
        // live chat: per visitor IP
        \Illuminate\Support\Facades\RateLimiter::for('chat', fn ($request) => [
            \Illuminate\Cache\RateLimiting\Limit::perMinute(15)->by('chat:'.$request->ip()),
            \Illuminate\Cache\RateLimiting\Limit::perDay(300)->by('chat-day:'.$request->ip()),
        ]);
        \Illuminate\Support\Facades\RateLimiter::for('chat-poll', fn ($request) => \Illuminate\Cache\RateLimiting\Limit::perMinute(40)->by('chat-poll:'.$request->ip()));
        \Illuminate\Support\Facades\RateLimiter::for('website-forms', fn ($request) => \Illuminate\Cache\RateLimiting\Limit::perMinute(120)->by('forms:'.$client($request)));

        Route::prefix('api/v1')->middleware('api')->name('api.')->group(base_path('routes/api.php'));

        // Missing translation -> show any language that has a value
        Translatable::fallback(fallbackAny: true);

        // Super admin can do everything
        Gate::before(function ($user, $ability) {
            return $user instanceof User && $user->isSuperAdmin() ? true : null;
        });

        Event::listen(Login::class, function (Login $event) {
            if ($event->user instanceof User) {
                // A language picked on the login screen wins and becomes the user's preference
                $chosen = session('locale');
                if ($chosen && $chosen !== $event->user->locale) {
                    $event->user->locale = $chosen;
                } elseif ($event->user->locale) {
                    session(['locale' => $event->user->locale]);
                }
                $event->user->last_login_at = now();
                $event->user->saveQuietly();
            }
        });
    }
}

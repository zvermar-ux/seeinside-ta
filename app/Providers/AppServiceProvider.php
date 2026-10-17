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

        // Every admin table: row actions (edit, duplicate, delete…) as compact icons at the start of the row
        Table::configureUsing(function (Table $table) {
            $table->actionsPosition(ActionsPosition::BeforeColumns)
                ->striped((bool) \App\Support\Branding::get('table_striped'));
            $perPage = (int) \App\Support\Branding::get('per_page');
            if (in_array($perPage, [10, 25, 50, 100], true)) {
                $table->paginated([10, 25, 50, 100])->defaultPaginationPageOption($perPage);
            }
        });

        // Where pop-up notifications appear (Settings → Appearance)
        [$vertical, $horizontal] = explode('-', (string) \App\Support\Branding::get('notifications_position', 'top-right')) + [1 => 'right'];
        \Filament\Notifications\Livewire\Notifications::verticalAlignment(match ($vertical) {
            'bottom' => \Filament\Support\Enums\VerticalAlignment::End,
            default => \Filament\Support\Enums\VerticalAlignment::Start,
        });
        \Filament\Notifications\Livewire\Notifications::alignment(match ($horizontal) {
            'left' => \Filament\Support\Enums\Alignment::Start,
            'center' => \Filament\Support\Enums\Alignment::Center,
            default => \Filament\Support\Enums\Alignment::End,
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
        ]);

        // Website API (WordPress plugin)
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

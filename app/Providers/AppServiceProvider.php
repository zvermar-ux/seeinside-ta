<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Events\Login;
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

<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Http\Middleware\SetLocale;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Filament\SpatieLaravelTranslatablePlugin;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->passwordReset()
            ->profile(isSimple: false)
            ->brandName(fn () => config('app.name'))
            ->colors([
                'primary' => Color::Sky,
                'gray' => Color::Slate,
                'info' => Color::Indigo,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
            ])
            ->font('Inter')
            ->spa()
            ->unsavedChangesAlerts()
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth(MaxWidth::Full)
            ->navigationGroups([
                'offer' => NavigationGroup::make()->label(fn () => __('Offer')),
                'partners' => NavigationGroup::make()->label(fn () => __('Partners')),
                'administration' => NavigationGroup::make()->label(fn () => __('Administration')),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([Dashboard::class])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([])
            ->plugin(SpatieLaravelTranslatablePlugin::make()->defaultLocales(array_keys(SetLocale::LOCALES)))
            ->renderHook(PanelsRenderHook::USER_MENU_BEFORE, fn (): string => Blade::render('<x-locale-switcher />'))
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, fn (): string => Blade::render('<x-locale-switcher :center="true" />'))
            ->renderHook(PanelsRenderHook::STYLES_AFTER, fn () => view('filament.styles'))
            ->renderHook(PanelsRenderHook::SIDEBAR_FOOTER, fn (): string => Blade::render('<x-agency-badge />'))
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->middleware([SetLocale::class], isPersistent: true)
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}

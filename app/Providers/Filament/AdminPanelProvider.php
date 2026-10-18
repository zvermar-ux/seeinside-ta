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
use App\Support\Branding;
use Filament\Support\Facades\FilamentColor;
use Filament\Facades\Filament;
use Filament\FontProviders\LocalFontProvider;
use Filament\Enums\ThemeMode;
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
    public function boot(): void
    {
        // Settings that are read while the page is rendered (after login, so agency colours apply)
        Filament::serving(function () {
            $panel = Filament::getCurrentPanel();
            if (! $panel || $panel->getId() !== 'admin') {
                return;
            }

            \Illuminate\Support\Facades\Auth::user();   // agency's own look needs the user
            FilamentColor::register(Branding::colors());

            $panel->font(Branding::fontFamily(), Branding::fontUrl(), LocalFontProvider::class);

            $mode = Branding::get('theme_mode');
            $panel->darkMode($mode !== 'light', $mode === 'dark');
            $panel->defaultThemeMode(ThemeMode::tryFrom((string) Branding::get('default_theme')) ?? ThemeMode::System);

            $panel->maxContentWidth(MaxWidth::tryFrom((string) Branding::get('content_width')) ?? MaxWidth::Full);
            if ($w = Branding::get('sidebar_width')) {
                $panel->sidebarWidth($w);
            }
        });
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->passwordReset()
            ->profile(isSimple: false)
            // Look & feel comes from Settings → Appearance (App\Support\Branding); see also boot() below
            ->brandName(fn () => Branding::appName())
            ->brandLogo(fn () => Branding::brandLogo())
            ->darkModeBrandLogo(fn () => Branding::brandLogo(dark: true))
            ->brandLogoHeight(fn () => Branding::get('logo_height') ?: '2.25rem')
            ->favicon(fn () => Branding::url(Branding::get('favicon')))
            ->colors(fn () => Branding::colors())
            ->font('Inter')
            ->spa(fn () => (bool) Branding::get('spa'))
            ->unsavedChangesAlerts(fn () => (bool) Branding::get('unsaved_alerts'))
            ->breadcrumbs(fn () => (bool) Branding::get('breadcrumbs'))
            ->topNavigation(fn () => Branding::get('navigation') === 'top')
            ->sidebarCollapsibleOnDesktop(fn () => Branding::get('sidebar_collapse') === 'icons')
            ->sidebarFullyCollapsibleOnDesktop(fn () => Branding::get('sidebar_collapse') === 'full')
            ->maxContentWidth(MaxWidth::Full)
            ->navigationGroups([
                'offer' => NavigationGroup::make()->label(fn () => Branding::groupLabel('offer', __('Offer'))),
                'bookings' => NavigationGroup::make()->label(fn () => Branding::groupLabel('bookings', __('Bookings & requests'))),
                'ai' => NavigationGroup::make()->label(fn () => Branding::groupLabel('ai', __('AI & Live chat'))),
                'partners' => NavigationGroup::make()->label(fn () => Branding::groupLabel('partners', __('Partners'))),
                'settings' => NavigationGroup::make()->label(fn () => Branding::groupLabel('settings', __('Settings')))->collapsed(),
                'administration' => NavigationGroup::make()->label(fn () => Branding::groupLabel('administration', __('Administration'))),
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
            ->renderHook(PanelsRenderHook::STYLES_AFTER, fn () => view('filament.branding-styles'))
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => view('filament.branding-head'))
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => view('filament.library.assets'))
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE, fn () => view('filament.login-intro'))
            ->renderHook(PanelsRenderHook::FOOTER, fn () => view('filament.footer'))
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
            ->middleware([SetLocale::class, \App\Http\Middleware\ApplyBranding::class], isPersistent: true)
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}

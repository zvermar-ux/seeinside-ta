<?php

namespace App\Support;

use App\Models\Agency;
use App\Models\Setting;
use Filament\Support\Colors\Color;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Look & feel (Administration → Appearance).
 * - The super admin's settings apply to super admins and to the login page.
 * - An agency admin's settings apply to him and the operators of his agency; agencies that did not
 *   change anything see the standard look.
 */
class Branding
{
    public const KEY = 'appearance';

    public const DEFAULTS = [
        // brand
        'app_name' => null,
        'show_name_with_logo' => false,
        'logo' => null,
        'logo_dark' => null,
        'logo_height' => '2.25rem',
        'favicon' => null,
        'app_icon' => null,
        // login
        'login_background' => null,
        'login_layout' => 'centered',
        'login_title' => ['mk' => null, 'en' => null],
        'login_text' => ['mk' => null, 'en' => null],
        // colours
        'primary' => '#0ea5e9',
        'gray' => 'slate',
        'success' => '#10b981',
        'warning' => '#f59e0b',
        'danger' => '#f43f5e',
        'info' => '#6366f1',
        // theme & typography
        'theme_mode' => 'switchable',
        'default_theme' => 'system',
        'font' => 'Inter',
        'heading_font' => null,
        'heading_weight' => '700',
        'font_scale' => '100',
        'radius' => 'md',
        'background' => 'plain',
        // layout
        'navigation' => 'sidebar',
        'sidebar_style' => 'light',
        'sidebar_collapse' => 'icons',
        'sidebar_width' => '20rem',
        'content_width' => 'full',
        'spa' => true,
        'unsaved_alerts' => true,
        'breadcrumbs' => true,
        'table_striped' => true,
        'per_page' => 25,
        'notifications_position' => 'top-right',
        // icons & menu
        'icon_style' => 'outline',
        'active_icon_solid' => true,
        'nav' => [],
        'groups' => [],
        // footer & advanced
        'footer_text' => ['mk' => null, 'en' => null],
        'custom_css' => null,
        'allow_agency_branding' => true,
        'agency_name_as_brand' => false,
    ];

    /** Fonts with Cyrillic support (served by Bunny Fonts, GDPR friendly) */
    public const FONTS = [
        'Inter' => 'Inter',
        'Roboto' => 'Roboto',
        'Open Sans' => 'Open Sans',
        'Montserrat' => 'Montserrat',
        'Nunito' => 'Nunito',
        'Manrope' => 'Manrope',
        'Rubik' => 'Rubik',
        'Fira Sans' => 'Fira Sans',
        'Noto Sans' => 'Noto Sans',
        'IBM Plex Sans' => 'IBM Plex Sans',
        'Source Sans 3' => 'Source Sans 3',
        'PT Sans' => 'PT Sans',
        'Commissioner' => 'Commissioner',
        'Golos Text' => 'Golos Text',
        'Raleway' => 'Raleway',
        'Ubuntu' => 'Ubuntu',
        'Lora' => 'Lora (serif)',
        'Playfair Display' => 'Playfair Display (serif)',
        'Merriweather' => 'Merriweather (serif)',
        'system' => 'System (no download)',
    ];

    /** Tailwind palettes: 500 shade → Filament palette (exact shades instead of generated ones) */
    public const PALETTES = [
        '#0ea5e9' => 'Sky', '#3b82f6' => 'Blue', '#6366f1' => 'Indigo', '#8b5cf6' => 'Violet', '#a855f7' => 'Purple',
        '#d946ef' => 'Fuchsia', '#ec4899' => 'Pink', '#f43f5e' => 'Rose', '#ef4444' => 'Red', '#f97316' => 'Orange',
        '#f59e0b' => 'Amber', '#eab308' => 'Yellow', '#84cc16' => 'Lime', '#22c55e' => 'Green', '#10b981' => 'Emerald',
        '#14b8a6' => 'Teal', '#06b6d4' => 'Cyan',
    ];

    /** Ready-made colour combinations */
    public const PRESETS = [
        'sky' => ['label' => 'Ocean (default)', 'primary' => '#0ea5e9', 'gray' => 'slate', 'info' => '#6366f1'],
        'blue' => ['label' => 'Corporate blue', 'primary' => '#3b82f6', 'gray' => 'gray', 'info' => '#06b6d4'],
        'emerald' => ['label' => 'Fresh green', 'primary' => '#10b981', 'gray' => 'zinc', 'info' => '#0ea5e9'],
        'orange' => ['label' => 'Sunset orange', 'primary' => '#f97316', 'gray' => 'stone', 'info' => '#0ea5e9'],
        'rose' => ['label' => 'Rose', 'primary' => '#f43f5e', 'gray' => 'neutral', 'info' => '#8b5cf6'],
        'violet' => ['label' => 'Violet night', 'primary' => '#8b5cf6', 'gray' => 'zinc', 'info' => '#06b6d4'],
        'teal' => ['label' => 'Mediterranean teal', 'primary' => '#14b8a6', 'gray' => 'slate', 'info' => '#3b82f6'],
        'amber' => ['label' => 'Golden sand', 'primary' => '#f59e0b', 'gray' => 'stone', 'info' => '#14b8a6'],
    ];

    /** Settings an agency admin may change for the users of his agency */
    public const AGENCY_KEYS = [
        'app_name', 'show_name_with_logo', 'logo', 'logo_dark', 'logo_height', 'favicon',
        'primary', 'gray', 'success', 'warning', 'danger', 'info',
        'theme_mode', 'default_theme', 'font', 'heading_font', 'heading_weight', 'font_scale', 'radius', 'background',
        'navigation', 'sidebar_style', 'sidebar_collapse', 'sidebar_width', 'content_width', 'spa', 'unsaved_alerts',
        'breadcrumbs', 'table_striped', 'per_page', 'notifications_position', 'footer_text',
        'icon_style', 'active_icon_solid', 'nav', 'groups',
    ];

    /** @var array<int, array> effective settings per agency id (0 = application) */
    protected static array $cache = [];

    protected static ?array $global = null;

    /** Application-wide values (defaults + what the super admin saved) */
    public static function global(): array
    {
        if (static::$global !== null) {
            return static::$global;
        }
        $stored = Setting::get(self::KEY, []);

        return static::$global = array_replace(self::DEFAULTS, is_array($stored) ? $stored : []);
    }

    /** Effective values for the logged-in user (application look + his agency's changes) */
    public static function all(): array
    {
        $user = Auth::hasUser() ? Auth::user() : null;
        if (! $user || $user->isSuperAdmin()) {
            return static::global();      // super admins and the login page
        }
        if ($agency = static::agency()) {
            return static::forAgency($agency->id);
        }
        if (! isset(static::$cache[-1])) {
            static::$cache[-1] = static::agencyBase();
        }

        return static::$cache[-1];         // agency looks switched off / user without agency
    }

    public static function forAgency(?int $agencyId): array
    {
        $key = (int) $agencyId;
        if (isset(static::$cache[$key])) {
            return static::$cache[$key];
        }

        return static::$cache[$key] = $key ? array_replace(static::agencyBase(), static::agencyOverrides($key)) : static::global();
    }

    /**
     * Starting point of an agency's look: the standard look (the super admin's own choices apply only to
     * super admins), plus the application-wide parts such as the login page.
     */
    public static function agencyBase(): array
    {
        return array_replace(self::DEFAULTS, array_diff_key(static::global(), array_flip(self::AGENCY_KEYS)));
    }

    public static function agencyKey(int $agencyId): string
    {
        return self::KEY.'.agency.'.$agencyId;
    }

    /** Only what the agency changed compared to the application look */
    public static function agencyOverrides(int $agencyId): array
    {
        $stored = Setting::get(static::agencyKey($agencyId), []);
        $overrides = array_intersect_key(is_array($stored) ? $stored : [], array_flip(self::AGENCY_KEYS));

        // older per-agency colour / logo fields
        $agency = Agency::query()->withoutGlobalScopes()->find($agencyId);
        foreach (['primary' => $agency?->brand_color, 'logo' => $agency?->brand_logo, 'logo_dark' => $agency?->brand_logo_dark] as $k => $v) {
            if (filled($v) && ! array_key_exists($k, $overrides)) {
                $overrides[$k] = $v;
            }
        }

        return $overrides;
    }

    public static function saveForAgency(int $agencyId, array $values): void
    {
        $base = static::agencyBase();
        $diff = [];
        foreach (self::AGENCY_KEYS as $k) {
            if (array_key_exists($k, $values) && $values[$k] != ($base[$k] ?? null)) {
                $diff[$k] = $values[$k];
            }
        }
        Setting::put(static::agencyKey($agencyId), $diff);
        Agency::query()->withoutGlobalScopes()->whereKey($agencyId)->update(['brand_color' => null, 'brand_logo' => null, 'brand_logo_dark' => null]);
        static::flush();
    }

    public static function resetAgency(int $agencyId): void
    {
        Setting::forget(static::agencyKey($agencyId));
        Agency::query()->withoutGlobalScopes()->whereKey($agencyId)->update(['brand_color' => null, 'brand_logo' => null, 'brand_logo_dark' => null]);
        static::flush();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return Arr::get(static::all(), $key, $default);
    }

    public static function save(array $values): void
    {
        $clean = array_intersect_key($values, self::DEFAULTS);
        Setting::put(self::KEY, $clean);
        static::flush();
    }

    /** Forget values kept in memory (long-running processes, tests) */
    public static function flush(): void
    {
        static::$cache = [];
        static::$global = null;
        Setting::forgetMemo();
    }

    public static function reset(): void
    {
        Setting::forget(self::KEY);
        static::flush();
    }

    public static function url(?string $path): ?string
    {
        return filled($path) ? Storage::disk('public')->url($path) : null;
    }

    /** Localised text field ({"mk":…,"en":…}) */
    public static function text(string $key): ?string
    {
        $v = static::get($key);
        if (! is_array($v)) {
            return filled($v) ? (string) $v : null;
        }

        return $v[app()->getLocale()] ?? null ?: (collect($v)->first(fn ($x) => filled($x)) ?: null);
    }

    /* ---------------- agency override ---------------- */

    public static function agency(): ?Agency
    {
        if (! (static::global()['allow_agency_branding'] ?? false) || ! Auth::hasUser()) {
            return null;
        }
        $user = Auth::user();

        return $user && ! $user->isSuperAdmin() ? $user->agency : null;
    }

    public static function appName(): string
    {
        if (static::global()['agency_name_as_brand'] && ($agency = static::agency()) && ! array_key_exists('app_name', static::agencyOverrides($agency->id))) {
            return $agency->name;
        }

        return static::get('app_name') ?: config('app.name');
    }

    public static function logo(bool $dark = false): ?string
    {
        return static::url($dark ? static::get('logo_dark') : static::get('logo'));
    }

    /** Logo for the panel header; with "show name" the name is printed next to the image */
    public static function brandLogo(bool $dark = false): mixed
    {
        $url = static::logo($dark) ?? ($dark ? static::logo() : null);
        if (! $url) {
            return null;
        }
        if (! static::get('show_name_with_logo')) {
            return $url;
        }

        return new \Illuminate\Support\HtmlString(
            '<span style="display:inline-flex;align-items:center;gap:.6rem;height:100%">'
            .'<img src="'.e($url).'" alt="" style="height:100%;width:auto">'
            .'<span class="ta-brand-name">'.e(static::appName()).'</span></span>'
        );
    }

    /* ---------------- colours ---------------- */

    public static function palette(?string $value, array $fallback): array
    {
        if (blank($value)) {
            return $fallback;
        }
        $hex = strtolower(trim($value));
        if (isset(self::PALETTES[$hex])) {
            return constant(Color::class.'::'.self::PALETTES[$hex]);
        }
        if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/', $hex)) {
            return Color::hex($hex);
        }
        if (preg_match('/^rgb/', $hex)) {
            return Color::rgb($hex);
        }

        return $fallback;
    }

    public static function colors(): array
    {
        $grays = ['slate' => Color::Slate, 'gray' => Color::Gray, 'zinc' => Color::Zinc, 'neutral' => Color::Neutral, 'stone' => Color::Stone];
        $primary = static::get('primary');

        return [
            'primary' => static::palette($primary, Color::Sky),
            'gray' => $grays[static::get('gray')] ?? Color::Slate,
            'info' => static::palette(static::get('info'), Color::Indigo),
            'success' => static::palette(static::get('success'), Color::Emerald),
            'warning' => static::palette(static::get('warning'), Color::Amber),
            'danger' => static::palette(static::get('danger'), Color::Rose),
        ];
    }

    /** Hex of the primary colour (theme-color meta, manifest) */
    public static function primaryHex(): string
    {
        $c = static::get('primary');

        return preg_match('/^#[0-9a-f]{6}$/i', (string) $c) ? $c : '#0ea5e9';
    }

    /* ---------------- fonts ---------------- */

    public static function fontFamily(): string
    {
        $f = static::get('font') ?: 'Inter';

        return $f === 'system' ? 'system-ui' : $f;
    }

    /** One stylesheet for the body font and the optional heading font */
    public static function fontUrl(): ?string
    {
        $families = collect([static::get('font'), static::get('heading_font')])
            ->filter(fn ($f) => filled($f) && $f !== 'system' && isset(self::FONTS[$f]))->unique();
        if ($families->isEmpty()) {
            return null;
        }

        return 'https://fonts.bunny.net/css?family='.$families
            ->map(fn ($f) => strtolower(str_replace(' ', '-', $f)).':400,500,600,700,800')
            ->implode('|').'&display=swap';
    }

    /* ---------------- navigation ---------------- */

    public const ICONS = [
        'squares-2x2', 'home', 'chart-bar', 'chart-pie', 'globe-europe-africa', 'globe-alt', 'map', 'map-pin', 'paper-airplane',
        'building-office', 'building-office-2', 'building-storefront', 'home-modern', 'key', 'ticket', 'calendar', 'calendar-days',
        'clock', 'bolt', 'fire', 'star', 'sparkles', 'heart', 'gift', 'photo', 'film', 'camera', 'musical-note', 'trophy',
        'identification', 'document-text', 'document-check', 'clipboard-document-check', 'clipboard-document-list', 'inbox-arrow-down',
        'inbox', 'envelope', 'chat-bubble-left-right', 'phone', 'user', 'users', 'user-group', 'user-circle', 'briefcase',
        'currency-euro', 'banknotes', 'credit-card', 'receipt-percent', 'shopping-cart', 'tag', 'truck', 'sun', 'cloud',
        'link', 'cog-6-tooth', 'adjustments-horizontal', 'wrench-screwdriver', 'shield-check', 'lock-closed', 'paint-brush',
        'swatch', 'arrow-down-tray', 'arrow-path', 'list-bullet', 'queue-list', 'table-cells', 'flag', 'lifebuoy', 'megaphone',
        'newspaper', 'rocket-launch', 'academic-cap', 'beaker', 'cube', 'puzzle-piece', 'server-stack', 'signal', 'wifi',
    ];

    public static function styledIcon(?string $icon, bool $active = false): ?string
    {
        if (blank($icon) || ! str_starts_with($icon, 'heroicon-')) {
            return $icon;
        }
        $style = $active && static::get('active_icon_solid') ? 'solid' : static::get('icon_style');
        $prefix = ['solid' => 'heroicon-s-', 'mini' => 'heroicon-m-'][$style] ?? 'heroicon-o-';

        return preg_replace('/^heroicon-[osm]-/', $prefix, $icon);
    }

    public static function navOverride(string $class, string $field): mixed
    {
        $value = (static::get('nav') ?? [])[class_basename($class)][$field] ?? null;

        return filled($value) ? $value : null;
    }

    public static function navLabel(string $class): ?string
    {
        return static::navOverride($class, 'label_'.app()->getLocale());
    }

    public static function groupLabel(string $key, string $default): string
    {
        return (static::get('groups') ?? [])[$key][app()->getLocale()] ?? null ?: $default;
    }
}

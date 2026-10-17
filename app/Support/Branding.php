<?php

namespace App\Support;

use App\Models\Agency;
use App\Models\Setting;
use Filament\Support\Colors\Color;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Look & feel of the whole application (super admin → "Appearance"), optionally overridden per agency
 * (logo / colour) when the super admin allows it.
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
        'allow_agency_branding' => false,
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

    protected static ?array $cache = null;

    public static function all(): array
    {
        if (static::$cache !== null) {
            return static::$cache;
        }

        $stored = Setting::get(self::KEY, []);

        return static::$cache = array_replace(self::DEFAULTS, is_array($stored) ? $stored : []);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return Arr::get(static::all(), $key, $default);
    }

    public static function save(array $values): void
    {
        $clean = array_intersect_key($values, self::DEFAULTS);
        Setting::put(self::KEY, $clean);
        static::$cache = null;
    }

    /** Forget values kept in memory (long-running processes, tests) */
    public static function flush(): void
    {
        static::$cache = null;
        Setting::forgetMemo();
    }

    public static function reset(): void
    {
        Setting::forget(self::KEY);
        static::$cache = null;
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
        if (! static::get('allow_agency_branding')) {
            return null;
        }
        $user = Auth::user();

        return $user && ! $user->isSuperAdmin() ? $user->agency : null;
    }

    public static function appName(): string
    {
        if (static::get('agency_name_as_brand') && ($agency = static::agency())) {
            return $agency->name;
        }

        return static::get('app_name') ?: config('app.name');
    }

    public static function logo(bool $dark = false): ?string
    {
        $agency = static::agency();
        $path = $dark
            ? ($agency?->brand_logo_dark ?: $agency?->brand_logo ?: static::get('logo_dark'))
            : ($agency?->brand_logo ?: static::get('logo'));

        return static::url($path);
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
        $primary = static::agency()?->brand_color ?: static::get('primary');

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
        $c = static::agency()?->brand_color ?: static::get('primary');

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

<?php

namespace App\Filament\Concerns;

use App\Support\Branding;
use Illuminate\Contracts\Support\Htmlable;

/** Menu icon, label and order can be changed in Settings → Appearance (icon style applies everywhere) */
trait CustomizableNavigation
{
    public static function defaultNavigationIcon(): ?string
    {
        return static::$navigationIcon ?? null;
    }

    public static function defaultNavigationLabel(): string
    {
        return parent::getNavigationLabel();
    }

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return Branding::styledIcon(Branding::navOverride(static::class, 'icon') ?? static::defaultNavigationIcon());
    }

    public static function getActiveNavigationIcon(): string | Htmlable | null
    {
        return Branding::styledIcon(Branding::navOverride(static::class, 'icon') ?? static::defaultNavigationIcon(), active: true);
    }

    public static function getNavigationLabel(): string
    {
        return Branding::navLabel(static::class) ?? static::defaultNavigationLabel();
    }

    public static function getNavigationSort(): ?int
    {
        $sort = Branding::navOverride(static::class, 'sort');

        return $sort !== null ? (int) $sort : parent::getNavigationSort();
    }
}

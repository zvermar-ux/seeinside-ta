<?php

namespace App\Filament\Support;

use App\Models\Agency;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Facades\Auth;

/** Agency selector shown only to the super admin (agency users are bound to their own agency). */
class AgencyField
{
    public static function isSuper(): bool
    {
        return (bool) Auth::user()?->isSuperAdmin();
    }

    /** Agency id the current form works with */
    public static function current(?callable $get = null): ?int
    {
        if (! static::isSuper()) {
            return Auth::user()?->agency_id;
        }

        $id = $get ? $get('agency_id') : null;

        return $id ? (int) $id : null;
    }

    public static function select(): Select
    {
        return Select::make('agency_id')
            ->label(__('Agency'))
            ->options(fn () => Agency::orderBy('name')->pluck('name', 'id'))
            ->default(fn () => Agency::count() === 1 ? Agency::value('id') : null)
            ->searchable()
            ->required(fn () => static::isSuper())
            ->live()
            ->visible(fn () => static::isSuper());
    }

    public static function column(): TextColumn
    {
        return TextColumn::make('agency.name')
            ->label(__('Agency'))
            ->sortable()
            ->toggleable()
            ->visible(fn () => static::isSuper());
    }

    public static function filter(): SelectFilter
    {
        return SelectFilter::make('agency_id')
            ->label(__('Agency'))
            ->relationship('agency', 'name')
            ->preload()
            ->visible(fn () => static::isSuper());
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\GuardedByPermission;
use App\Filament\Resources\ImportRunResource\Pages;
use App\Filament\Support\AgencyField;
use App\Models\ImportRun;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ImportRunResource extends Resource
{
    use GuardedByPermission {
        can as protected permissionCan;
    }

    protected static ?string $model = ImportRun::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static ?string $navigationGroup = 'administration';

    protected static ?int $navigationSort = 9;

    public static function permissionModule(): string
    {
        return 'imports';
    }

    public static function can(string $action, ?Model $record = null): bool
    {
        // History is read-only; imports are started from the server console
        return in_array($action, ['viewAny', 'view'], true) && static::permissionCan($action, $record);
    }

    public static function getModelLabel(): string
    {
        return mb_strtolower(__('Import'));
    }

    public static function getPluralModelLabel(): string
    {
        return __('Imports');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('started_at')->label(__('Started'))->dateTime('d.m.Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('source')->label(__('Source'))->badge()->formatStateUsing(fn ($state) => strtoupper($state)),
                AgencyField::column(),
                Tables\Columns\TextColumn::make('status')->label(__('Status'))->badge()
                    ->formatStateUsing(fn ($state) => __(ucfirst($state)))
                    ->color(fn ($state) => match ($state) { 'finished' => 'success', 'failed' => 'danger', default => 'warning' }),
                Tables\Columns\TextColumn::make('stats')->label(__('Result'))->wrap()
                    ->state(fn (ImportRun $r) => collect($r->stats ?? [])->map(fn ($v, $k) => __(str_replace('_', ' ', ucfirst($k))).': '.$v)->implode(' · ')),
                Tables\Columns\TextColumn::make('duration')->label(__('Duration'))
                    ->state(fn (ImportRun $r) => $r->finished_at && $r->started_at ? $r->started_at->diffForHumans($r->finished_at, true) : '—'),
                Tables\Columns\TextColumn::make('message')->label(__('Message'))->limit(60)->toggleable()->placeholder('—'),
            ])
            ->defaultSort('started_at', 'desc')
            ->poll('10s');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListImportRuns::route('/')];
    }
}

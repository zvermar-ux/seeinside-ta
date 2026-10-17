<?php

namespace App\Filament\Resources;

use App\Enums\GroupType;
use App\Filament\Concerns\GuardedByPermission;
use App\Filament\Resources\GroupResource\Pages;
use App\Filament\Support\AgencyField;
use App\Models\Group;
use App\Filament\Support\Duplicate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GroupResource extends Resource
{
    use GuardedByPermission, Translatable, \App\Filament\Concerns\CustomizableNavigation;

    protected static ?string $model = Group::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationGroup = 'offer';

    protected static ?int $navigationSort = 2;

    public static function permissionModule(): string
    {
        return 'groups';
    }

    public static function getModelLabel(): string
    {
        return mb_strtolower(__('Group / destination'));
    }

    public static function getPluralModelLabel(): string
    {
        return __('Groups & destinations');
    }

    public static function getRecordTitle(?\Illuminate\Database\Eloquent\Model $record): string
    {
        return $record?->display_name ?? static::getModelLabel();
    }

    /** Options for parent / group pickers */
    public static function optionsFor(GroupType $type, ?int $agencyId, ?int $exceptId = null): array
    {
        return Group::query()
            ->ofType($type)
            ->when($agencyId, fn (Builder $q) => $q->where('agency_id', $agencyId))
            ->when($exceptId, fn (Builder $q) => $q->whereKeyNot($exceptId))
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(fn (Group $g) => [$g->id => $g->display_name])
            ->sort()
            ->all();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(3)->schema([
                Forms\Components\Section::make()->columnSpan(2)->columns(2)->schema([
                    AgencyField::select(),
                    Forms\Components\ToggleButtons::make('type')->label(__('Type'))
                        ->options(GroupType::class)->inline()->required()->live()
                        ->default(fn () => request()->query('type', GroupType::Group->value)),
                    Forms\Components\TextInput::make('name')->label(__('Name'))->required()->maxLength(255),
                    Forms\Components\Select::make('parent_id')->label(__('Parent group'))
                        ->options(fn (Get $get, ?Group $record) => static::optionsFor(
                            GroupType::tryFrom((string) ($get('type') instanceof GroupType ? $get('type')->value : $get('type'))) ?? GroupType::Group,
                            AgencyField::current($get),
                            $record?->id,
                        ))
                        ->searchable(),
                    Forms\Components\RichEditor::make('description')->label(__('Description'))
                        ->columnSpanFull()
                        ->fileAttachmentsDisk('public')->fileAttachmentsDirectory('groups/attachments'),
                ]),
                Forms\Components\Section::make()->columnSpan(1)->schema([
                    Forms\Components\FileUpload::make('image')->label(__('Image'))
                        ->image()->imageEditor()->disk('public')->directory('groups'),
                    Forms\Components\Toggle::make('is_active')->label(__('Active'))->default(true),
                    Forms\Components\Toggle::make('show_on_homepage')->label(__('Show on homepage')),
                    Forms\Components\Toggle::make('show_in_offer')->label(__('Show in offer')),
                    Forms\Components\TextInput::make('sort_order')->label(__('Order'))->numeric()->default(0),
                    Forms\Components\TextInput::make('external_url')->label(__('External link'))->url(),
                    Forms\Components\Toggle::make('use_external_url')->label(__('Open external link instead of the page')),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->columns([
                Tables\Columns\ImageColumn::make('image')->label('')->disk('public')->square()->size(44),
                Tables\Columns\TextColumn::make('name')->label(__('Name'))->weight('semibold')
                    ->state(fn (Group $r) => $r->display_name)
                    ->searchable(query: fn (Builder $query, string $search) => $query->where('name', 'like', '%'.$search.'%')),
                Tables\Columns\TextColumn::make('parent_name')->label(__('Parent group'))
                    ->state(fn (Group $r) => $r->parent?->display_name)
                    ->placeholder('—')->badge()->color('gray')->icon('heroicon-m-folder')
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderByRaw('parent_id is null')->orderBy('parent_id', $direction)),
                Tables\Columns\TextColumn::make('type')->label(__('Type'))->badge()->color(fn (Group $r) => $r->type === GroupType::Destination ? 'success' : 'info'),
                AgencyField::column(),
                Tables\Columns\TextColumn::make('objects_count')->label(__('Arrangements & hotels'))->badge()->color('gray')
                    ->state(fn (Group $r) => $r->type === GroupType::Destination ? $r->destination_objects_count : $r->group_objects_count),
                Tables\Columns\ToggleColumn::make('is_active')->label(__('Active')),
                Tables\Columns\ToggleColumn::make('show_on_homepage')->label(__('Homepage'))->toggleable(),
                Tables\Columns\ToggleColumn::make('show_in_offer')->label(__('Offer'))->toggleable(),
                Tables\Columns\IconColumn::make('use_external_url')->label(__('Link'))->boolean()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->with('parent')->withCount([
                'travelObjects as group_objects_count',
                'destinationObjects as destination_objects_count',
            ]))
            ->filters([
                AgencyField::filter(),
                Tables\Filters\TernaryFilter::make('has_parent')->label(__('Parent group'))
                    ->trueLabel(__('With parent group'))->falseLabel(__('Main groups only (no parent)'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('parent_id'),
                        false: fn (Builder $query) => $query->whereNull('parent_id'),
                        blank: fn (Builder $query) => $query,
                    ),
                Tables\Filters\SelectFilter::make('parent_id')->label(__('Belongs to group'))->searchable()
                    ->options(fn () => Group::query()->whereIn('id', Group::query()->whereNotNull('parent_id')->select('parent_id'))->get()->mapWithKeys(fn (Group $g) => [$g->id => $g->display_name])->sort()),
                Tables\Filters\TernaryFilter::make('is_active')->label(__('Active')),
                Tables\Filters\TernaryFilter::make('show_on_homepage')->label(__('Show on homepage')),
            ])
            ->actions([
                Tables\Actions\Action::make('objects')->label(__('Offer'))->icon('heroicon-o-list-bullet')->color('gray')->iconButton()->tooltip(__('Offer'))
                    ->url(fn (Group $r) => TravelObjectResource::getUrl('index', ['tableFilters' => [
                        $r->type === GroupType::Destination ? 'destination_id' : 'group_id' => ['value' => $r->id],
                    ]])),
                Tables\Actions\EditAction::make(),
                Duplicate::action(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('activate')->label(__('Activate'))->icon('heroicon-o-check-circle')
                        ->action(fn ($records) => $records->each->update(['is_active' => true]))->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('deactivate')->label(__('Deactivate'))->icon('heroicon-o-x-circle')
                        ->action(fn ($records) => $records->each->update(['is_active' => false]))->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGroups::route('/'),
            'create' => Pages\CreateGroup::route('/create'),
            'edit' => Pages\EditGroup::route('/{record}/edit'),
        ];
    }
}

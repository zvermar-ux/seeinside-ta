<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\GuardedByPermission;
use App\Filament\Resources\CustomRecordResource\Pages;
use App\Filament\Support\AgencyField;
use App\Models\Agency;
use App\Models\CustomRecord;
use App\Models\RecordType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Records of the types the super admin defined (one menu item per type) */
class CustomRecordResource extends Resource
{
    use GuardedByPermission;

    protected static ?string $model = CustomRecord::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $navigationGroup = 'offer';

    protected static ?string $slug = 'other-offers';

    public static function permissionModule(): string
    {
        return 'custom_records';
    }

    public static function getModelLabel(): string
    {
        return mb_strtolower(__('Record'));
    }

    public static function getPluralModelLabel(): string
    {
        return __('Other offers');
    }

    /** Types the current user may work with */
    public static function types()
    {
        static $types = null;
        $types ??= RecordType::query()->where('is_active', true)->orderBy('sort_order')->get();

        return $types->filter(fn (RecordType $t) => Agency::moduleOn('custom:'.$t->slug))->values();
    }

    public static function currentType(): ?RecordType
    {
        $slug = request()->query('type');
        if (! $slug && ($id = request()->route('record'))) {
            $slug = CustomRecord::query()->find($id)?->recordType?->slug;
        }

        return $slug ? RecordType::where('slug', $slug)->first() : null;
    }

    public static function can(string $action, ?Model $record = null): bool
    {
        if ($record instanceof CustomRecord && $record->recordType && ! Agency::moduleOn('custom:'.$record->recordType->slug)) {
            return false;
        }
        if ($action === 'viewAny' && static::types()->isEmpty()) {
            return false;
        }

        return static::canPermission($action, $record);
    }

    protected static function canPermission(string $action, ?Model $record): bool
    {
        $u = \Illuminate\Support\Facades\Auth::user();
        if (! $u) {
            return false;
        }
        if ($u->isSuperAdmin()) {
            return true;
        }
        if ($record && (int) $record->getAttribute('agency_id') !== (int) $u->agency_id) {
            return false;
        }

        return $u->can(in_array($action, ['viewAny', 'view'], true) ? 'custom_records.view' : 'custom_records.manage');
    }

    /** one menu item per type (e.g. "Трансфери", "Rent-a-car") */
    public static function getNavigationItems(): array
    {
        $current = request()->routeIs(static::getRouteBaseName().'.*') ? static::currentType()?->slug : null;

        return static::types()->map(fn (RecordType $t) => NavigationItem::make($t->label())
            ->group(static::getNavigationGroup())
            ->icon($t->icon ?: 'heroicon-o-squares-2x2')
            ->isActiveWhen(fn () => $current === $t->slug)
            ->sort(50 + (int) $t->sort_order)
            ->url(static::getUrl('index', ['type' => $t->slug])))->all();
    }

    /** Form fields of a type's own fields (stored in "data") */
    public static function dataFields(?RecordType $t): array
    {
        if (! $t) {
            return [];
        }
        $out = [];
        foreach ($t->fieldDefs() as $f) {
            $k = 'data.'.$f['key'];
            $label = ($f['label'][app()->getLocale()] ?? null) ?: ($f['label']['mk'] ?? $f['key']);
            $req = (bool) ($f['required'] ?? false);
            $opts = collect((array) ($f['options'] ?? []))->filter()->mapWithKeys(fn ($o) => [$o => $o])->all();
            $two = fn ($component) => [
                $component($k.'.mk')->label($label.' (МК)')->required($req),
                $component($k.'.en')->label($label.' (EN)'),
            ];
            $c = match ($f['type'] ?? 'text') {
                'text' => $two(fn ($n) => Forms\Components\TextInput::make($n)->suffix($f['suffix'] ?? null)),
                'textarea', 'list' => $two(fn ($n) => Forms\Components\Textarea::make($n)->rows(3)->helperText(($f['type'] ?? '') === 'list' ? __('One item per line') : null)),
                'richtext' => $two(fn ($n) => Forms\Components\RichEditor::make($n)->toolbarButtons(['bold', 'italic', 'bulletList', 'orderedList', 'link', 'h3'])),
                'number' => [Forms\Components\TextInput::make($k)->label($label)->numeric()->suffix($f['suffix'] ?? null)->required($req)],
                'price' => [Forms\Components\TextInput::make($k)->label($label)->numeric()->prefix(fn (Get $get) => $get('currency') ?: 'EUR')->required($req)],
                'date' => [Forms\Components\DatePicker::make($k)->label($label)->native(false)->displayFormat('d.m.Y')->required($req)],
                'datetime' => [Forms\Components\DateTimePicker::make($k)->label($label)->native(false)->displayFormat('d.m.Y H:i')->seconds(false)->required($req)],
                'time' => [Forms\Components\TimePicker::make($k)->label($label)->seconds(false)->required($req)],
                'select' => [Forms\Components\Select::make($k)->label($label)->options($opts)->required($req)],
                'multiselect' => [Forms\Components\Select::make($k)->label($label)->options($opts)->multiple()->required($req)],
                'toggle' => [Forms\Components\Toggle::make($k)->label($label)->inline(false)],
                'image' => [Forms\Components\FileUpload::make($k)->label($label)->image()->disk('public')->directory('custom')->required($req)],
                'gallery' => [Forms\Components\FileUpload::make($k)->label($label)->image()->multiple()->reorderable()->disk('public')->directory('custom')->panelLayout('grid')->columnSpanFull()],
                'file' => [Forms\Components\FileUpload::make($k)->label($label)->disk('public')->directory('custom')->acceptedFileTypes(['application/pdf', 'image/*'])->openable()->downloadable()],
                'url' => [Forms\Components\TextInput::make($k)->label($label)->url()->required($req)],
                'email' => [Forms\Components\TextInput::make($k)->label($label)->email()->required($req)],
                'phone' => [Forms\Components\TextInput::make($k)->label($label)->tel()->required($req)],
                'location' => [Forms\Components\TextInput::make($k)->label($label)->placeholder('41.99,21.43 '.__('or address'))->required($req)],
                default => [Forms\Components\TextInput::make($k)->label($label)],
            };
            array_push($out, ...$c);
        }

        return $out;
    }

    public static function form(Form $form): Form
    {
        $typeFor = fn (Get $get) => RecordType::find($get('record_type_id'));

        return $form->schema([
            Forms\Components\Grid::make(3)->schema([
                Forms\Components\Group::make()->columnSpan(2)->schema([
                    Forms\Components\Section::make()->columns(2)->schema([
                        AgencyField::select()->columnSpanFull(),
                        Forms\Components\Select::make('record_type_id')->label(__('Type'))->required()->live()->columnSpanFull()
                            ->options(fn () => static::types()->mapWithKeys(fn ($t) => [$t->id => $t->label()]))
                            ->default(fn ($livewire) => RecordType::where('slug', $livewire->type ?? request()->query('type'))->value('id'))
                            ->disabled(fn (?CustomRecord $record) => (bool) $record)->dehydrated()
                            ->hidden(fn (?CustomRecord $record, Get $get, $livewire) => ! $record && $get('record_type_id') && filled($livewire->type ?? null)),
                        Forms\Components\TextInput::make('title.mk')->label(__('Title').' (МК)')->required(),
                        Forms\Components\TextInput::make('title.en')->label(__('Title').' (EN)'),
                        Forms\Components\Textarea::make('short_description.mk')->label(__('Short description').' (МК)')->rows(2),
                        Forms\Components\Textarea::make('short_description.en')->label(__('Short description').' (EN)')->rows(2),
                    ]),
                    Forms\Components\Section::make(fn (Get $get) => $typeFor($get)?->singularLabel() ?? __('Details'))->columns(2)
                        ->visible(fn (Get $get) => (bool) $typeFor($get)?->fieldDefs())
                        ->schema(fn (Get $get) => static::dataFields($typeFor($get))),
                    Forms\Components\Section::make(__('Description'))->collapsible()->schema([
                        Forms\Components\Tabs::make('d')->tabs([
                            Forms\Components\Tabs\Tab::make('МК')->schema([Forms\Components\RichEditor::make('description.mk')->hiddenLabel()]),
                            Forms\Components\Tabs\Tab::make('EN')->schema([Forms\Components\RichEditor::make('description.en')->hiddenLabel()]),
                        ]),
                    ]),
                ]),
                Forms\Components\Group::make()->columnSpan(1)->schema([
                    Forms\Components\Section::make(__('Price & booking'))->schema([
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('price_from')->label(fn (Get $get) => __('Price').(($p = $typeFor($get)?->price_label) ? ' ('.__(str_replace('_', ' ', $p)).')' : ''))->numeric(),
                            Forms\Components\Select::make('currency')->label(__('Currency'))->options(['EUR' => 'EUR', 'MKD' => 'MKD', 'USD' => 'USD'])->default('EUR')->live(),
                        ]),
                        Forms\Components\Toggle::make('booking_enabled')->label(__('Online booking / inquiry'))->default(true)
                            ->visible(fn (Get $get) => (bool) $typeFor($get)?->bookable),
                        Forms\Components\Toggle::make('is_active')->label(__('Active on website'))->default(true),
                        Forms\Components\Toggle::make('is_featured')->label(__('Featured')),
                        Forms\Components\TextInput::make('sort_order')->label(__('Order'))->numeric()->default(0),
                        Forms\Components\TextInput::make('slug')->label(__('URL (slug)'))->helperText(__('Created automatically from the title.')),
                    ]),
                    Forms\Components\Section::make(__('Photos'))->schema([
                        Forms\Components\FileUpload::make('cover_image')->label(__('Main photo'))->image()->imageEditor()->disk('public')->directory('custom'),
                        Forms\Components\FileUpload::make('gallery')->label(__('Gallery'))->image()->multiple()->reorderable()->maxFiles(40)->disk('public')->directory('custom')->panelLayout('grid'),
                    ]),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return static::tableFor($table, static::currentType());
    }

    public static function tableFor(Table $table, ?RecordType $type): Table
    {
        $listFields = collect($type?->fieldDefs() ?? [])->filter(fn ($f) => ! empty($f['show_in_list']))->take(4);

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('recordType')->when($type, fn ($q) => $q->where('record_type_id', $type->id)))
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                Tables\Columns\ImageColumn::make('cover_image')->label('')->disk('public')->square()->size(48),
                Tables\Columns\TextColumn::make('title')->label(__('Title'))->state(fn (CustomRecord $r) => $r->tr('title'))->weight('medium')
                    ->searchable(query: fn ($query, string $search) => $query->where('title', 'like', "%{$search}%"))
                    ->description(fn (CustomRecord $r) => $type ? null : $r->recordType?->label()),
                ...$listFields->map(fn ($f) => Tables\Columns\TextColumn::make('field_'.$f['key'])->label(($f['label'][app()->getLocale()] ?? null) ?: ($f['label']['mk'] ?? $f['key']))
                    ->state(fn (CustomRecord $r) => collect($r->displayFields(false))->firstWhere('key', $f['key'])['value'][app()->getLocale()] ?? null)
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : $state)->placeholder('—')->limit(40))->all(),
                Tables\Columns\TextColumn::make('price_from')->label(__('Price'))->formatStateUsing(fn ($state, CustomRecord $r) => number_format((float) $state, 2, ',', '.').' '.$r->currency)->placeholder('—'),
                Tables\Columns\ToggleColumn::make('is_active')->label(__('Active')),
                AgencyField::column(),
            ])
            ->filters([AgencyField::filter()])
            ->actions([Tables\Actions\EditAction::make()->iconButton(), Tables\Actions\ReplicateAction::make()->iconButton()
                ->mutateRecordDataUsing(fn (array $data) => array_merge($data, ['slug' => null, 'is_active' => false]))
                ->beforeReplicaSaved(fn (CustomRecord $replica) => $replica->slug = null), Tables\Actions\DeleteAction::make()->iconButton()])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()])
            ->emptyStateHeading(__('No records yet'));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomRecords::route('/'),
            'create' => Pages\CreateCustomRecord::route('/create'),
            'edit' => Pages\EditCustomRecord::route('/{record}/edit'),
        ];
    }
}

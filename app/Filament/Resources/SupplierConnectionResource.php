<?php

namespace App\Filament\Resources;

use App\Enums\SupplierProvider;
use App\Filament\Concerns\GuardedByPermission;
use App\Filament\Resources\SupplierConnectionResource\Pages;
use App\Filament\Support\AgencyField;
use App\Models\SupplierConnection;
use App\Filament\Support\Duplicate;
use App\Services\Hotels\HotelService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SupplierConnectionResource extends Resource
{
    use GuardedByPermission, \App\Filament\Concerns\CustomizableNavigation;

    protected static ?string $model = SupplierConnection::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $navigationIcon = 'heroicon-o-link';

    protected static ?string $navigationGroup = 'settings';

    protected static ?int $navigationSort = 5;

    public static function permissionModule(): string
    {
        return 'integrations';
    }

    public static function getModelLabel(): string
    {
        return mb_strtolower(__('Booking system connection'));
    }

    public static function getPluralModelLabel(): string
    {
        return __('Booking systems (API)');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(2)->schema([
                AgencyField::select(),
                Forms\Components\Select::make('provider')->label(__('System'))->options(SupplierProvider::class)->required()->live()
                    ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => blank($get('name')) ? $set('name', SupplierProvider::tryFrom((string) $state)?->getLabel()) : null),
                Forms\Components\TextInput::make('name')->label(__('Name'))->required(),
                Forms\Components\TextInput::make('base_url')->label(__('API address (URL)'))->url()
                    ->helperText(fn (Forms\Get $get) => $get('provider') === 'hotelbeds' ? __('Leave empty: chosen by the environment below.') : null),
                Forms\Components\TextInput::make('username')->label(__('Username / agency code'))
                    ->visible(fn (Forms\Get $get) => $get('provider') !== 'hotelbeds'),
                Forms\Components\KeyValue::make('credentials')->label(__('Access keys'))->columnSpanFull()
                    ->keyLabel(__('Name'))->valueLabel(__('Value'))
                    ->visible(fn (Forms\Get $get) => $get('provider') !== 'hotelbeds')
                    ->helperText(__('API key, secret, password… stored encrypted. An empty value keeps the saved one.')),

                // Hotelbeds (APItude): keys from developer.hotelbeds.com → My API keys
                Forms\Components\Section::make('Hotelbeds')->icon('heroicon-o-building-office-2')->columns(2)->compact()
                    ->visible(fn (Forms\Get $get) => $get('provider') === 'hotelbeds')
                    ->description(__('Keys from developer.hotelbeds.com → My API keys (Hotel API). Test keys only work in the test environment and allow few requests per day.'))
                    ->schema([
                        Forms\Components\TextInput::make('credentials.api_key')->label('API key')->required(),
                        Forms\Components\TextInput::make('credentials.secret')->label(__('Secret'))->password()->revealable()
                            ->placeholder(fn (?SupplierConnection $record) => filled($record?->credentials['secret'] ?? null) ? '••••••••  '.__('(saved – leave empty to keep)') : null),
                        Forms\Components\Select::make('settings.environment')->label(__('Environment'))->selectablePlaceholder(false)->default('test')
                            ->options(['test' => __('Test (api.test.hotelbeds.com)'), 'live' => __('Live (api.hotelbeds.com)')]),
                        Forms\Components\TextInput::make('settings.markup_percent')->label(__('Agency margin %'))->numeric()->default(10)->suffix('%')
                            ->helperText(__('Added to the net price of every rate (a hotel can have its own margin).')),
                        Forms\Components\TagsInput::make('settings.countries')->label(__('Countries'))->placeholder('GR, BG, TR, AL, ME')
                            ->helperText(__('Two-letter codes of the countries whose destinations are imported.')),
                        Forms\Components\TextInput::make('settings.tolerance')->label(__('Price tolerance % when booking'))->numeric()->default(2)->suffix('%'),
                        Forms\Components\Toggle::make('settings.auto_book')->label(__('Book automatically'))->inline(false)
                            ->helperText(__('Off: a website request waits in Bookings and you book it with "Book at the supplier". On: booked at once (the agency pays the supplier).')),
                    ]),
                Forms\Components\TextInput::make('settings.availability_url')->label(__('Availability address (JSON)'))->columnSpanFull()
                    ->placeholder('https://partner.mk/api/availability?hotel={hotel}&from={from}&to={to}')
                    ->helperText(__('For systems that give free rooms as a simple JSON feed. {hotel} = hotel code, {from}/{to} = dates. The access key is taken from "api_key" in the keys above.'))
                    ->visible(fn (Forms\Get $get) => in_array($get('provider'), ['expert', 'other'], true)),
                Forms\Components\Toggle::make('is_active')->label(__('Active')),
                Forms\Components\Textarea::make('notes')->label(__('Notes'))->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label(__('Name'))->weight('semibold'),
                Tables\Columns\TextColumn::make('provider')->label(__('System'))->badge(),
                AgencyField::column(),
                Tables\Columns\TextColumn::make('last_sync_at')->label(__('Last sync'))->since()->placeholder('—'),
                Tables\Columns\ToggleColumn::make('is_active')->label(__('Active')),
            ])
            ->actions([
                Tables\Actions\Action::make('test')->label(__('Test'))->icon('heroicon-o-signal')->color('gray')
                    ->visible(fn (SupplierConnection $r) => HotelService::supports($r))
                    ->action(function (SupplierConnection $r) {
                        $res = HotelService::driver($r)->test($r);
                        Notification::make()->title($res['ok'] ? __('Connection works') : __('Connection failed'))->body($res['message'])->{$res['ok'] ? 'success' : 'danger'}()->persistent()->send();
                    }),
                Tables\Actions\Action::make('destinations')->label(__('Import destinations'))->icon('heroicon-o-map')->color('gray')
                    ->visible(fn (SupplierConnection $r) => HotelService::supports($r))
                    ->form(fn (SupplierConnection $r) => [
                        Forms\Components\TagsInput::make('countries')->label(__('Countries'))->required()->default($r->settings['countries'] ?? ['GR', 'BG', 'TR'])
                            ->helperText(__('Two-letter codes, e.g. GR, BG, TR, AL, ME, EG.')),
                    ])
                    ->action(function (SupplierConnection $r, array $data) {
                        try {
                            $n = app(HotelService::class)->importDestinations($r, $data['countries']);
                            $r->forceFill(['settings' => array_merge((array) $r->settings, ['countries' => array_values($data['countries'])])])->save();
                            Notification::make()->title(__(':n destinations imported', ['n' => $n]))->body(__('Turn on the ones you sell in Hotels (API) → Destinations, then import their hotels.'))->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title(__('Import failed'))->body($e->getMessage())->danger()->persistent()->send();
                        }
                    }),
                Tables\Actions\EditAction::make()
                    // secrets are never sent to the browser; an empty secret keeps the saved one
                    ->mutateRecordDataUsing(function (array $data, SupplierConnection $record): array {
                        $cred = (array) $record->credentials;
                        $data['credentials'] = $record->provider?->value === 'hotelbeds'
                            ? ['api_key' => $cred['api_key'] ?? '', 'secret' => '']
                            : array_map(fn () => '', $cred);

                        return $data;
                    })
                    ->using(function (SupplierConnection $record, array $data): SupplierConnection {
                        $old = (array) $record->credentials;
                        $new = (array) ($data['credentials'] ?? []);
                        foreach ($new as $k => $v) {
                            if ($v === '' || $v === null) {
                                $new[$k] = $old[$k] ?? '';
                            }
                        }
                        $data['credentials'] = array_filter($new, fn ($v) => $v !== '');
                        $data['settings'] = array_merge((array) $record->settings, (array) ($data['settings'] ?? []));
                        $record->update($data);

                        return $record;
                    }),
                Duplicate::action(), Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateDescription(__('Hotelbeds, Filos, Bros, Seayou, People of the World, Expert, GOL IBE… The live connection is built once the supplier gives API access.'));
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageSupplierConnections::route('/')];
    }
}

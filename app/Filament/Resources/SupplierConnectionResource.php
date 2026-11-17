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
                    ->visible(fn (Forms\Get $get) => ! in_array($get('provider'), ['hotelbeds', 'mcs'], true))
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
                // the old Aries system (MCS eTours B2B API) while both systems are used
                Forms\Components\Section::make(__('Old Aries system'))->icon('heroicon-o-arrows-right-left')->columns(2)->compact()
                    ->visible(fn (Forms\Get $get) => $get('provider') === 'mcs')
                    ->description(__('API address e.g. https://aries.travel/api/apib2b. Linked tours and apartments get their dates and prices from there (the content stays ours), and every new booking of them is sent there at once.'))
                    ->schema([
                        Forms\Components\TextInput::make('credentials.password')->label(__('Password'))->password()->revealable()
                            ->placeholder(fn (?SupplierConnection $record) => filled($record?->credentials['password'] ?? null) ? '••••••••  '.__('(saved – leave empty to keep)') : null),
                        Forms\Components\Toggle::make('settings.verify_ssl')->label(__('Check the SSL certificate'))->default(true)->inline(false)
                            ->helperText(__('Turn off when the old server has an SSL certificate problem: the connection stays encrypted (https), only the certificate is not checked.')),
                        Forms\Components\Toggle::make('settings.push_bookings')->label(__('Send new bookings to the old system'))->default(true)->inline(false),
                        Forms\Components\Toggle::make('settings.sync_prices')->label(__('Take dates and prices from the old system'))->default(true)->inline(false),
                        Forms\Components\TextInput::make('settings.days_ahead')->label(__('Days ahead for dates and prices'))->numeric()->default(365)->minValue(7)->maxValue(400),
                        Forms\Components\TextInput::make('settings.concurrency')->label(__('Searches at the same time'))->numeric()->default(1)->minValue(1)->maxValue(8)
                            ->helperText(__('1 is safest: too many requests can get our server blocked by the old one.')),
                        Forms\Components\TextInput::make('settings.pause_ms')->label(__('Pause between searches (ms)'))->numeric()->default(500)->minValue(0)->maxValue(5000),
                        Forms\Components\TextInput::make('settings.timeout')->label(__('Wait for an answer (seconds)'))->numeric()->default(90)->minValue(20)->maxValue(300),
                        Forms\Components\CheckboxList::make('settings.months')->label(__('Months with terms (searched)'))->columns(6)->columnSpanFull()
                            ->options(collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => \Illuminate\Support\Carbon::create(2000, $m, 1)->translatedFormat('M')])->all())
                            ->default(range(4, 10))->helperText(__('Only these months are searched for package terms (fewer searches). Departures in other months are not changed.')),
                        Forms\Components\TextInput::make('settings.markup_percent')->label(__('Change of the prices taken %'))->numeric()->default(0)->suffix('%')
                            ->helperText(__('0 = the same prices as in the old system.')),
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
            ->poll('15s')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label(__('Name'))->weight('semibold'),
                Tables\Columns\TextColumn::make('provider')->label(__('System'))->badge(),
                AgencyField::column(),
                Tables\Columns\TextColumn::make('last_sync_at')->label(__('Last sync'))->since()->placeholder('—')->wrap()
                    ->description(function (SupplierConnection $r) {
                        if ($r->provider !== SupplierProvider::Mcs) {
                            return null;
                        }
                        if ($p = \App\Services\Mcs\McsService::progress($r)) {
                            return '⏳ '.__('Sync running').': '.$p;
                        }
                        $x = $r->settings['last_sync_result'] ?? null;
                        if (! $x) {
                            return null;
                        }

                        // short line; the whole report opens with the "Report" button
                        return ! empty($x['errors'])
                            ? '⚠ '.__('With errors – see Report')
                            : '✓ '.__(':d tour dates, :t room prices', ['d' => $x['departures'] ?? 0, 't' => $x['term_prices'] ?? 0]).(! empty($x['notes']) ? ' · '.__('notes: :n', ['n' => count($x['notes'])]) : '');
                    }),
                Tables\Columns\ToggleColumn::make('is_active')->label(__('Active')),
            ])
            ->actions([
                Tables\Actions\Action::make('test')->label(__('Test'))->icon('heroicon-o-signal')->color('gray')
                    ->visible(fn (SupplierConnection $r) => HotelService::supports($r))
                    ->action(function (SupplierConnection $r) {
                        $res = HotelService::driver($r)->test($r);
                        Notification::make()->title($res['ok'] ? __('Connection works') : __('Connection failed'))->body($res['message'])->{$res['ok'] ? 'success' : 'danger'}()->persistent()->send();
                    }),
                // what the booking system answers for one destination and dates (no cache, no website filters)
                Tables\Actions\Action::make('trySearch')->label(__('Trial search'))->icon('heroicon-o-magnifying-glass')->color('gray')
                    ->visible(fn (SupplierConnection $r) => HotelService::supports($r))
                    ->modalSubmitActionLabel(__('Search'))
                    ->form(fn (SupplierConnection $r) => [
                        Forms\Components\Select::make('destination')->label(__('Destination'))->searchable()->required()
                            ->options(fn () => \App\Models\SupplierDestination::query()->where('supplier_connection_id', $r->id)
                                ->orderByDesc('is_active')->orderBy('name')->get()->mapWithKeys(fn ($d) => [$d->code => $d->name.' ('.$d->code.')'.($d->country_name ? ' – '.$d->country_name : '')])),
                        Forms\Components\TextInput::make('hotel')->label(__('Hotel code (optional)'))->numeric(),
                        Forms\Components\DatePicker::make('from')->label(__('Check-in'))->required()->native(false)->displayFormat('d.m.Y')->default(now()->addDays(30)),
                        Forms\Components\TextInput::make('nights')->label(__('Nights'))->numeric()->minValue(1)->maxValue(30)->default(3)->required(),
                        Forms\Components\TextInput::make('adults')->label(__('Adults'))->numeric()->minValue(1)->maxValue(8)->default(2)->required(),
                    ])
                    ->action(function (SupplierConnection $r, array $data) {
                        $from = \Illuminate\Support\Carbon::parse($data['from']);
                        $q = ['from' => $from->toDateString(), 'to' => $from->copy()->addDays((int) $data['nights'])->toDateString(), 'rooms' => [['adults' => (int) $data['adults'], 'children_ages' => []]]];
                        $q += filled($data['hotel'] ?? null) ? ['hotels' => [(string) $data['hotel']]] : ['destination' => $data['destination']];
                        try {
                            $found = HotelService::driver($r)->search($r, $q);
                        } catch (\Throwable $e) {
                            Notification::make()->title(__('Search failed'))->body($e->getMessage())->danger()->persistent()->send();

                            return;
                        }
                        $lines = collect($found)->take(8)->map(function ($h) use ($r) {
                            $rates = collect($h['rooms'])->flatMap(fn ($x) => $x['rates']);
                            $min = $rates->sortBy('net')->first();

                            return '• '.$h['name'].' ('.$h['code'].')'.($min ? ' – '.__('net').' '.number_format($min['net'], 2).' '.$h['currency'].', '.__('for the client').' '.number_format(HotelService::sell($min, $r), 0).' '.$h['currency'] : '');
                        })->implode("\n");
                        Notification::make()
                            ->title(__(':n hotels with free rooms', ['n' => count($found)]).' · '.$from->format('d.m.Y').' + '.(int) $data['nights'].' '.__('nights'))
                            ->body($found ? new \Illuminate\Support\HtmlString(nl2br(e($lines))) : __('The booking system has no free rooms for this search. Try other dates, fewer nights or another destination.'))
                            ->{$found ? 'success' : 'warning'}()->persistent()->send();
                    }),
                Tables\Actions\Action::make('mcsTest')->label(__('Test'))->icon('heroicon-o-signal')->color('gray')
                    ->visible(fn (SupplierConnection $r) => $r->provider === SupplierProvider::Mcs)
                    ->action(function (SupplierConnection $r) {
                        $res = \App\Services\Mcs\McsService::test($r);
                        Notification::make()->title($res['ok'] ? __('Connection works') : __('Connection failed'))->body($res['message'])->{$res['ok'] ? 'success' : 'danger'}()->persistent()->send();
                    }),
                Tables\Actions\Action::make('mcsCatalog')->label(__('Import offer & link'))->icon('heroicon-o-link')->color('gray')
                    ->visible(fn (SupplierConnection $r) => $r->provider === SupplierProvider::Mcs)
                    ->requiresConfirmation()->modalDescription(__('Reads the hotels/apartments and packages of the old system and links our apartments and tours with the same name. Our texts, photos and rooms are not changed.'))
                    ->action(function (SupplierConnection $r) {
                        try {
                            @set_time_limit(600);
                            $n = \App\Services\Mcs\McsService::importCatalog($r);
                            Notification::make()->title(__('Offer of the old system read'))->success()->persistent()
                                ->body(__(':h hotels, :p packages (villas and tours). Newly linked: :a apartments, :r rooms, :t tours. Check them in Apartments (column Aries, filter "Linked to Aries").', ['h' => $n['hotels'], 'p' => $n['packages'], 'a' => $n['linked_accommodations'], 'r' => $n['linked_rooms'], 't' => $n['linked_tours']]))->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title(__('Import failed'))->body($e->getMessage())->danger()->persistent()->send();
                        }
                    }),
                Tables\Actions\Action::make('mcsProbe')->label(__('Trial search'))->icon('heroicon-o-magnifying-glass')->color('gray')
                    ->visible(fn (SupplierConnection $r) => $r->provider === SupplierProvider::Mcs)
                    ->modalSubmitActionLabel(__('Search'))
                    ->form([
                        Forms\Components\Select::make('kind')->label(__('Type'))->options(['package' => __('Package (villa in fixed terms, tour)'), 'hotel' => __('Hotel')])->default('package')->required(),
                        Forms\Components\TextInput::make('code')->label(__('Id in the old system'))->required()->placeholder('151'),
                        Forms\Components\DatePicker::make('date')->label(__('Check-in'))->required()->native(false)->displayFormat('d.m.Y')
                            ->helperText(__('For a villa in fixed terms: the first day of a term.')),
                        Forms\Components\TextInput::make('nights')->label(__('Nights (hotel only)'))->numeric()->default(7),
                    ])
                    ->action(function (SupplierConnection $r, array $data) {
                        $t0 = microtime(true);
                        try {
                            $res = \App\Services\Mcs\McsService::probe($r, $data['kind'], trim($data['code']), \Illuminate\Support\Carbon::parse($data['date'])->toDateString(), (int) $data['nights']);
                        } catch (\Throwable $e) {
                            Notification::make()->title(__('Search failed').' ('.round(microtime(true) - $t0, 1).' s)')->body($e->getMessage())->danger()->persistent()->send();

                            return;
                        }
                        $res = ['seconds' => round(microtime(true) - $t0, 1)] + $res;
                        $json = json_encode($res, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                        Notification::make()->title(__(':n offers', ['n' => count($res['rows'])]).' · '.$res['seconds'].' s')->{$res['rows'] ? 'success' : 'warning'}()->persistent()
                            ->body(new \Illuminate\Support\HtmlString('<pre style="font-size:.7rem;max-height:22rem;overflow:auto;white-space:pre-wrap">'.e(mb_substr($json, 0, 6000)).'</pre>'))->send();
                    }),
                Tables\Actions\Action::make('mcsReport')->label(__('Report'))->icon('heroicon-o-document-magnifying-glass')->color('gray')
                    ->visible(fn (SupplierConnection $r) => $r->provider === SupplierProvider::Mcs && ! empty($r->settings['last_sync_result']))
                    ->modalHeading(__('Last sync with the old system'))->modalSubmitAction(false)->modalCancelActionLabel(__('Close'))->modalWidth('2xl')
                    ->modalContent(function (SupplierConnection $r) {
                        $x = (array) ($r->settings['last_sync_result'] ?? []);
                        $row = fn ($k, $v) => '<tr><td style="padding:.3rem .8rem .3rem 0;color:#6b7280;white-space:nowrap;vertical-align:top">'.e($k).'</td><td style="padding:.3rem 0">'.$v.'</td></tr>';
                        $list = fn ($items, $color = null) => $items ? '<ul style="margin:0;padding-left:1.1rem'.($color ? ';color:'.$color : '').'">'.collect($items)->map(fn ($i) => '<li>'.e($i).'</li>')->implode('').'</ul>' : '—';
                        $html = '<table style="width:100%;font-size:.9rem;border-collapse:collapse">'
                            .$row(__('Time'), e(! empty($x['at']) ? \Illuminate\Support\Carbon::parse($x['at'])->timezone(config('app.timezone'))->format('d.m.Y H:i') : '—').(! empty($x['days']) ? ' · '.e(__(':d days ahead', ['d' => $x['days']])) : ''))
                            .$row(__('Searches'), e(($x['calls'] ?? 0).' · '.__('offers found: :o', ['o' => $x['results'] ?? 0])))
                            .$row(__('Tours'), e(__(':d dates (no longer offered: :s)', ['d' => $x['departures'] ?? 0, 's' => $x['sold_out'] ?? 0])))
                            .$row(__('Apartments'), e(__(':n new terms, :t room prices, stop sale: :x', ['n' => $x['terms'] ?? 0, 't' => $x['term_prices'] ?? 0, 'x' => $x['stop_sale'] ?? 0])))
                            .$row(__('Errors'), $list($x['errors'] ?? [], '#dc2626'))
                            .$row(__('Notes'), $list($x['notes'] ?? []))
                            .'</table>';

                        return new \Illuminate\Support\HtmlString($html);
                    }),
                Tables\Actions\Action::make('mcsPrices')->label(__('Sync prices now'))->icon('heroicon-o-arrow-path')->color('gray')
                    ->visible(fn (SupplierConnection $r) => $r->provider === SupplierProvider::Mcs)
                    ->form([Forms\Components\TextInput::make('days')->label(__('Days ahead'))->numeric()->default(365)->minValue(7)->maxValue(400)
                        ->helperText(__('Runs in the background on the server (a few minutes for 365 days); the progress and the result are shown in this list. Every night it runs by itself.'))])
                    ->action(function (SupplierConnection $r, array $data) {
                        \App\Services\Mcs\McsService::requestSync($r, (int) $data['days']);
                        Notification::make()->title(__('The sync starts within a minute'))->body(__('You can leave this page; the progress is shown under "Last sync".'))->success()->send();
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
                        if ($record->provider === SupplierProvider::Mcs) {
                            $data['settings']['months'] ??= range(1, 12);
                            $data['settings']['concurrency'] ??= 1;
                            $data['settings']['pause_ms'] ??= 500;
                            $data['settings']['timeout'] ??= 90;
                            $data['settings']['days_ahead'] ??= 365;
                        }
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

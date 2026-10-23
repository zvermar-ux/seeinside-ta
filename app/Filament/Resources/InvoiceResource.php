<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\GuardedByPermission;
use App\Filament\Resources\InvoiceResource\Pages;
use App\Filament\Support\AgencyField;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Invoice;
use App\Services\Documents\Documents;
use App\Services\Finance\Finance;
use App\Services\Finance\InvoiceBuilder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/** Outgoing proformas (понуди) and invoices (фактури) */
class InvoiceResource extends Resource
{
    use GuardedByPermission, \App\Filament\Concerns\CustomizableNavigation;

    protected static ?string $model = Invoice::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $navigationIcon = 'heroicon-o-document-currency-euro';

    protected static ?string $navigationGroup = 'finance';

    protected static ?int $navigationSort = 1;

    public static function permissionModule(): string
    {
        return 'finance';
    }

    public static function getModelLabel(): string
    {
        return mb_strtolower(__('Invoice'));
    }

    public static function getPluralModelLabel(): string
    {
        return __('Invoices & proformas');
    }

    public static function money($v, ?string $cur = null): string
    {
        return number_format((float) $v, 2, ',', '.').($cur ? ' '.$cur : '');
    }

    public static function form(Form $form): Form
    {
        $lineTotal = function (Get $get, Set $set) {
            $set('total', round((float) ($get('qty') ?? 1) * (float) ($get('price') ?? 0), 2));
        };

        return $form->schema([
            Forms\Components\Grid::make(3)->schema([
                Forms\Components\Section::make()->columnSpan(2)->columns(2)->schema([
                    AgencyField::select(),
                    Forms\Components\ToggleButtons::make('type')->label(__('Document'))->inline()->required()->default('invoice')->live()
                        ->options(['proforma' => __('Proforma invoice'), 'invoice' => __('Invoice')])
                        ->disabled(fn (?Invoice $record) => (bool) $record),
                    Forms\Components\Placeholder::make('number_info')->label(__('Number'))
                        ->content(fn (?Invoice $record) => $record?->number ?? __('Assigned automatically on save')),
                    Forms\Components\Select::make('client_id')->label(__('Client / company'))->searchable()->live()->columnSpanFull()
                        ->getSearchResultsUsing(fn (string $search) => ClientResource::searchOptions($search))
                        ->getOptionLabelUsing(fn ($value) => Client::find($value)?->label())
                        ->createOptionForm(ClientResource::fields(true))
                        ->createOptionUsing(function (array $data, Get $get) {
                            $data['agency_id'] = AgencyField::current($get) ?? Auth::user()->agency_id;

                            return Client::create($data)->getKey();
                        })
                        ->afterStateUpdated(function ($state, Set $set) {
                            if ($c = Client::find($state)) {
                                $set('client_name', $c->display_name);
                                $set('client_address', trim(($c->address ?? '').', '.($c->city ?? ''), ' ,'));
                                $set('client_tax_number', $c->tax_number);
                            }
                        }),
                    Forms\Components\TextInput::make('client_name')->label(__('Name on the invoice'))->required(),
                    Forms\Components\TextInput::make('client_tax_number')->label(__('Tax no. (ЕДБ)')),
                    Forms\Components\TextInput::make('client_address')->label(__('Address'))->columnSpanFull(),
                    Forms\Components\Select::make('booking_id')->label(__('Booking'))->searchable()->live()->columnSpanFull()
                        ->getSearchResultsUsing(fn (string $search) => Booking::query()->where(fn ($q) => $q->where('reference', 'like', "%{$search}%")->orWhere('customer_name', 'like', "%{$search}%"))
                            ->latest()->limit(30)->get()->mapWithKeys(fn (Booking $b) => [$b->id => $b->reference.' · '.$b->customer_name.' · '.$b->itemTitle()])->all())
                        ->getOptionLabelUsing(fn ($value) => ($b = Booking::find($value)) ? $b->reference.' · '.$b->customer_name.' · '.$b->itemTitle() : null)
                        ->hintAction(Forms\Components\Actions\Action::make('fill')->label(__('Fill from the booking'))->icon('heroicon-m-sparkles')
                            ->visible(fn (Get $get) => filled($get('booking_id')))
                            ->action(function (Get $get, Set $set) {
                                $b = Booking::find($get('booking_id'));
                                if (! $b) {
                                    return;
                                }
                                $d = InvoiceBuilder::fromBooking($b, $get('type') ?: 'invoice', null, $get('currency') ?: null);
                                foreach (['client_id', 'client_name', 'client_address', 'client_tax_number', 'currency', 'exchange_rate'] as $k) {
                                    $set($k, $d[$k]);
                                }
                                $set('lines', collect($d['lines'])->mapWithKeys(fn ($l) => [(string) \Illuminate\Support\Str::uuid() => $l + ['total' => $l['price']]])->all());
                            })),
                ]),
                Forms\Components\Section::make()->columnSpan(1)->schema([
                    Forms\Components\DatePicker::make('issue_date')->label(__('Date'))->native(false)->displayFormat('d.m.Y')->default(now())->required(),
                    Forms\Components\DatePicker::make('due_date')->label(__('Due date'))->native(false)->displayFormat('d.m.Y')
                        ->default(fn () => now()->addDays((int) (Agency::find(Auth::user()->agency_id)?->doc('invoice_days', 8) ?? 8))),
                    Forms\Components\Select::make('currency')->label(__('Currency'))->options(['MKD' => 'MKD', 'EUR' => 'EUR', 'USD' => 'USD'])->default('MKD')->required()->live(),
                    Forms\Components\TextInput::make('exchange_rate')->label(__('EUR rate'))->numeric()->step(0.0001)
                        ->default(fn () => Agency::find(Auth::user()->agency_id)?->eurRate()),
                    Forms\Components\Select::make('status')->label(__('Status'))->default('issued')->selectablePlaceholder(false)
                        ->options(fn (Get $get) => $get('type') === 'proforma'
                            ? ['draft' => __('Draft'), 'issued' => __('Issued'), 'paid' => __('Converted / paid'), 'cancelled' => __('Cancelled')]
                            : ['draft' => __('Draft'), 'issued' => __('Issued (paid status is automatic)'), 'cancelled' => __('Cancelled (storno)')]),
                    Forms\Components\Placeholder::make('paid_info')->label(__('Paid'))->visible(fn (?Invoice $record) => (bool) $record)
                        ->content(fn (?Invoice $record) => $record ? static::money($record->paid_amount, $record->currency).' · '.__('Owes').' '.static::money($record->balance(), $record->currency) : ''),
                ]),
            ]),
            Forms\Components\Section::make(__('Lines'))->schema([
                Forms\Components\Repeater::make('lines')->hiddenLabel()->columns(12)->defaultItems(1)->reorderable()->addActionLabel(__('Add line'))
                    ->live(debounce: 600)
                    ->schema([
                        Forms\Components\Textarea::make('description')->label(__('Description'))->rows(2)->required()->columnSpan(6),
                        Forms\Components\TextInput::make('qty')->label(__('Qty'))->numeric()->default(1)->columnSpan(1)->live(onBlur: true)->afterStateUpdated($lineTotal),
                        Forms\Components\TextInput::make('price')->label(__('Price (without VAT)'))->numeric()->required()->columnSpan(2)->live(onBlur: true)->afterStateUpdated($lineTotal),
                        Forms\Components\TextInput::make('vat')->label(__('VAT %'))->numeric()->columnSpan(1)
                            ->default(fn () => (float) (Agency::find(Auth::user()->agency_id)?->doc('vat_rate', 0) ?? 0)),
                        Forms\Components\TextInput::make('total')->label(__('Amount'))->disabled()->dehydrated()->columnSpan(2),
                    ]),
                Forms\Components\Grid::make(4)->schema([
                    Forms\Components\TextInput::make('discount')->label(__('Discount (rabat)'))->numeric()->default(0)->live(onBlur: true),
                    Forms\Components\Placeholder::make('sum_sub')->label(__('Base'))->content(fn (Get $get) => static::money(static::totals($get)['sub'], $get('currency'))),
                    Forms\Components\Placeholder::make('sum_vat')->label(__('VAT'))->content(fn (Get $get) => static::money(static::totals($get)['vat'], $get('currency'))),
                    Forms\Components\Placeholder::make('sum_total')->label(__('Total'))->content(fn (Get $get) => new \Illuminate\Support\HtmlString('<b style="font-size:1.15em">'.static::money(static::totals($get)['total'], $get('currency')).'</b>')),
                ]),
                Forms\Components\Textarea::make('note')->label(__('Note on the invoice'))->rows(2),
            ]),
        ]);
    }

    protected static function totals(Get $get): array
    {
        $sub = 0;
        $vat = 0;
        foreach ((array) $get('lines') as $l) {
            $net = (float) ($l['qty'] ?? 1) * (float) ($l['price'] ?? 0);
            $sub += $net;
            $vat += $net * (float) ($l['vat'] ?? 0) / 100;
        }

        return ['sub' => $sub, 'vat' => $vat, 'total' => $sub - (float) $get('discount') + $vat];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('number')->label(__('Number'))->searchable()->weight('medium')->sortable(['year', 'seq']),
                Tables\Columns\TextColumn::make('type')->label(__('Type'))->badge()
                    ->formatStateUsing(fn ($state) => $state === 'proforma' ? __('Proforma') : __('Invoice'))
                    ->color(fn ($state) => $state === 'proforma' ? 'gray' : 'info'),
                Tables\Columns\TextColumn::make('issue_date')->label(__('Date'))->date('d.m.Y')->sortable(),
                Tables\Columns\TextColumn::make('client_name')->label(__('Client'))->searchable()->limit(30),
                Tables\Columns\TextColumn::make('booking.reference')->label(__('Booking'))->placeholder('—')->toggleable()
                    ->url(fn (Invoice $r) => $r->booking_id ? BookingResource::getUrl('edit', ['record' => $r->booking_id]) : null),
                Tables\Columns\TextColumn::make('total')->label(__('Total'))->alignEnd()->sortable()->formatStateUsing(fn (Invoice $r) => static::money($r->total, $r->currency)),
                Tables\Columns\TextColumn::make('paid_amount')->label(__('Paid'))->alignEnd()->formatStateUsing(fn (Invoice $r) => static::money($r->paid_amount, $r->currency))->toggleable(),
                Tables\Columns\TextColumn::make('balance')->label(__('Owes'))->alignEnd()->state(fn (Invoice $r) => $r->status === 'cancelled' ? 0 : $r->balance())
                    ->formatStateUsing(fn ($state, Invoice $r) => static::money($state, $r->currency))->color(fn ($state) => $state > 0 ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('due_date')->label(__('Due date'))->date('d.m.Y')->sortable()
                    ->color(fn (Invoice $r) => $r->type === 'invoice' && $r->due_date?->isPast() && $r->balance() > 0 && $r->status !== 'cancelled' ? 'danger' : null),
                Tables\Columns\TextColumn::make('status')->label(__('Status'))->badge()->formatStateUsing(fn ($state) => Invoice::statuses()[$state] ?? $state)
                    ->color(fn ($state) => ['paid' => 'success', 'partial' => 'warning', 'cancelled' => 'danger', 'draft' => 'gray'][$state] ?? 'info'),
                Tables\Columns\IconColumn::make('sent_at')->label(__('Sent'))->boolean()->state(fn (Invoice $r) => (bool) $r->sent_at)->toggleable(),
                AgencyField::column(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->label(__('Type'))->options(['proforma' => __('Proformas'), 'invoice' => __('Invoices')]),
                Tables\Filters\SelectFilter::make('status')->label(__('Status'))->options(Invoice::statuses())->multiple(),
                Tables\Filters\Filter::make('unpaid')->label(__('Unpaid'))->query(fn (Builder $q) => $q->where('type', 'invoice')->whereIn('status', ['issued', 'partial'])),
                Tables\Filters\Filter::make('overdue')->label(__('Overdue'))->query(fn (Builder $q) => $q->where('type', 'invoice')->whereIn('status', ['issued', 'partial'])->whereDate('due_date', '<', today())),
                Tables\Filters\Filter::make('period')->form([
                    Forms\Components\DatePicker::make('from')->label(__('From'))->native(false)->displayFormat('d.m.Y'),
                    Forms\Components\DatePicker::make('to')->label(__('To'))->native(false)->displayFormat('d.m.Y'),
                ])->columns(2)->query(fn (Builder $q, array $data) => $q
                    ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('issue_date', '>=', $d))
                    ->when($data['to'] ?? null, fn ($q, $d) => $q->whereDate('issue_date', '<=', $d))),
                AgencyField::filter(),
            ])
            ->actions([
                Tables\Actions\Action::make('print')->iconButton()->icon('heroicon-o-printer')->tooltip(__('Print'))
                    ->url(fn (Invoice $r) => Documents::url('invoice', $r->id), shouldOpenInNewTab: true),
                ...static::recordActions(true),
                Tables\Actions\EditAction::make()->iconButton(),
            ])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()->visible(fn () => Auth::user()?->isSuperAdmin() || Auth::user()?->hasRole('agency_admin'))])
            ->paginated([25, 50, 100]);
    }

    /** Pay / convert / e-mail – as table row actions ($table) or page header actions */
    public static function recordActions(bool $table = false): array
    {
        $A = $table ? Tables\Actions\Action::class : \Filament\Actions\Action::class;

        return [
            $A::make('pay')->label(__('Register payment'))->icon('heroicon-o-banknotes')->color('success')->when($table, fn ($a) => $a->iconButton()->tooltip(__('Register payment')))
                ->visible(fn (Invoice $record) => $record->status !== 'cancelled' && $record->balance() > 0)
                ->modalHeading(fn (Invoice $record) => __('Payment for').' '.$record->title())
                ->fillForm(fn (Invoice $record) => ['amount' => $record->balance(), 'date' => now()->toDateString(), 'method' => 'cash'])
                ->form(fn (Invoice $record) => [
                    Forms\Components\TextInput::make('amount')->label(__('Amount'))->numeric()->required()->suffix($record->currency),
                    Forms\Components\DatePicker::make('date')->label(__('Date'))->native(false)->displayFormat('d.m.Y')->required(),
                    Forms\Components\Select::make('method')->label(__('Payment method'))->options(\App\Models\CashEntry::methods())->required()->live(),
                    Forms\Components\Select::make('register')->label(__('Cash register'))->visible(fn (Get $get) => $get('method') !== 'bank')
                        ->options(fn () => collect(Finance::registers(Agency::find($record->agency_id)))->except('bank')->all())
                        ->default(fn () => Finance::registerFor('cash', Agency::find($record->agency_id))),
                    Forms\Components\TextInput::make('note')->label(__('Note')),
                ])
                ->action(function (Invoice $record, array $data) {
                    Finance::payInvoice($record, (float) $data['amount'], $data['date'], $data['method'], $data['method'] === 'bank' ? 'bank' : ($data['register'] ?? null), $data['note'] ?? null);
                    Notification::make()->success()->title(__('Payment registered'))->send();
                })
                ->after(function (Invoice $record, $livewire) {
                    if ($livewire instanceof \Filament\Resources\Pages\EditRecord) {
                        $record->refresh();
                        $livewire->refreshFormData(['status', 'paid_amount']);
                    }
                }),
            $A::make('convert')->label(__('Make invoice'))->icon('heroicon-o-arrow-right-circle')->color('info')->when($table, fn ($a) => $a->iconButton()->tooltip(__('Make invoice from this proforma')))
                ->visible(fn (Invoice $record) => $record->type === 'proforma' && ! Invoice::where('proforma_id', $record->id)->exists() && $record->status !== 'cancelled')
                ->requiresConfirmation()->modalDescription(__('A final invoice with a new number is created with the same lines. Payments of the proforma move to the invoice.'))
                ->action(function (Invoice $record, $livewire) {
                    $inv = InvoiceBuilder::convert($record);
                    Notification::make()->success()->title($inv->title())->send();
                    $livewire->redirect(static::getUrl('edit', ['record' => $inv]));
                }),
            $A::make('email')->label(__('Send by e-mail'))->icon('heroicon-o-envelope')->color('gray')->when($table, fn ($a) => $a->iconButton()->tooltip(__('Send by e-mail')))
                ->fillForm(fn (Invoice $record) => ['to' => $record->client?->email ?: $record->booking?->customer_email, 'message' => __('Dear,').PHP_EOL.__('please find the document attached.')])
                ->form([
                    Forms\Components\TextInput::make('to')->label(__('To'))->email()->required(),
                    Forms\Components\TagsInput::make('cc')->label(__('Copy (CC)')),
                    Forms\Components\Textarea::make('message')->label(__('Message'))->rows(4),
                ])
                ->action(function (Invoice $record, array $data) {
                    $err = Documents::email('invoice', $record, $data['to'], $data['message'] ?? null, $data['cc'] ?? []);
                    $err ? Notification::make()->danger()->title(__('The e-mail was not sent'))->body($err)->persistent()->send()
                        : Notification::make()->success()->title(__('Sent to :to', ['to' => $data['to']]))->send();
                }),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'create' => Pages\CreateInvoice::route('/create'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\GuardedByPermission;
use App\Filament\Resources\SupplierInvoiceResource\Pages;
use App\Filament\Support\AgencyField;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\Client;
use App\Models\SupplierInvoice;
use App\Models\TourOperator;
use App\Services\Finance\Finance;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/** Incoming invoices (влезни фактури) from hotels, carriers, tour operators – what the agency owes */
class SupplierInvoiceResource extends Resource
{
    use GuardedByPermission, \App\Filament\Concerns\CustomizableNavigation;

    protected static ?string $model = SupplierInvoice::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?string $navigationGroup = 'finance';

    protected static ?int $navigationSort = 2;

    public static function permissionModule(): string
    {
        return 'finance';
    }

    public static function getModelLabel(): string
    {
        return mb_strtolower(__('Incoming invoice'));
    }

    public static function getPluralModelLabel(): string
    {
        return __('Incoming invoices');
    }

    public static function categories(): array
    {
        return ['accommodation' => __('Accommodation'), 'transport' => __('Bus transport'), 'flight' => __('Flight tickets'), 'guide' => __('Guide'),
            'tickets' => __('Tickets / entrance fees'), 'insurance' => __('Insurance'), 'visa' => __('Visa'), 'operator' => __('Tour operator'), 'other' => __('Other')];
    }

    /** Form fields – also used to add an incoming invoice directly from a booking */
    public static function fields(?Booking $booking = null): array
    {
        return [
            AgencyField::select()->visible(fn () => AgencyField::isSuper() && ! $booking),
            Forms\Components\Select::make('tour_operator_id')->label(__('Supplier (tour operator)'))->searchable()->live()
                ->options(fn (Get $get) => TourOperator::query()->when($booking?->agency_id ?? AgencyField::current($get), fn ($q, $a) => $q->where('agency_id', $a))->orderBy('name')->pluck('name', 'id'))
                ->afterStateUpdated(fn ($state, Forms\Set $set) => $state && $set('supplier_name', TourOperator::find($state)?->name)),
            Forms\Components\Select::make('client_id')->label(__('…or company from the client list'))->searchable()->live()
                ->getSearchResultsUsing(fn (string $search) => ClientResource::searchOptions($search, 'company'))
                ->getOptionLabelUsing(fn ($value) => Client::find($value)?->label())
                ->afterStateUpdated(fn ($state, Forms\Set $set) => $state && $set('supplier_name', Client::find($state)?->display_name)),
            Forms\Components\TextInput::make('supplier_name')->label(__('Supplier name'))->required(),
            Forms\Components\TextInput::make('number')->label(__('Invoice number'))->required(),
            Forms\Components\DatePicker::make('issue_date')->label(__('Date'))->native(false)->displayFormat('d.m.Y')->default(now())->required(),
            Forms\Components\DatePicker::make('due_date')->label(__('Due date'))->native(false)->displayFormat('d.m.Y'),
            Forms\Components\TextInput::make('amount')->label(__('Amount'))->numeric()->required(),
            Forms\Components\Select::make('currency')->label(__('Currency'))->options(['EUR' => 'EUR', 'MKD' => 'MKD', 'USD' => 'USD'])->default($booking?->currency ?? 'EUR')->required(),
            Forms\Components\TextInput::make('exchange_rate')->label(__('EUR rate'))->numeric()->step(0.0001)
                ->default(fn () => $booking?->exchange_rate ?: Agency::find($booking?->agency_id ?? Auth::user()->agency_id)?->eurRate()),
            Forms\Components\Select::make('category')->label(__('Cost type'))->options(static::categories()),
            Forms\Components\Select::make('booking_id')->label(__('For booking'))->searchable()->visible(! $booking)
                ->getSearchResultsUsing(fn (string $search) => Booking::query()->where(fn ($q) => $q->where('reference', 'like', "%{$search}%")->orWhere('customer_name', 'like', "%{$search}%"))
                    ->latest()->limit(30)->get()->mapWithKeys(fn (Booking $b) => [$b->id => $b->reference.' · '.$b->customer_name.' · '.$b->itemTitle()])->all())
                ->getOptionLabelUsing(fn ($value) => ($b = Booking::find($value)) ? $b->reference.' · '.$b->customer_name.' · '.$b->itemTitle() : null)
                ->helperText(__('Linked costs appear in the report of the offer as real costs.')),
            Forms\Components\MorphToSelect::make('costable')->label(__('…or for a whole offer (group / departure costs)'))->visible(! $booking)->searchable()
                ->types([
                    Forms\Components\MorphToSelect\Type::make(\App\Models\Tour::class)->label(__('Tour'))->getOptionLabelFromRecordUsing(fn ($r) => $r->tr('title')),
                    Forms\Components\MorphToSelect\Type::make(\App\Models\Accommodation::class)->label(__('Accommodation'))->titleAttribute('name'),
                    Forms\Components\MorphToSelect\Type::make(\App\Models\Event::class)->label(__('Event'))->getOptionLabelFromRecordUsing(fn ($r) => $r->tr('title')),
                ])->columnSpanFull(),
            Forms\Components\FileUpload::make('file')->label(__('Scan / PDF of the invoice'))->disk('public')->directory('supplier-invoices')
                ->acceptedFileTypes(['application/pdf', 'image/*'])->openable()->downloadable()->columnSpanFull(),
            Forms\Components\Textarea::make('note')->label(__('Note'))->rows(2)->columnSpanFull(),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(3)->schema(static::fields()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('issue_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('issue_date')->label(__('Date'))->date('d.m.Y')->sortable(),
                Tables\Columns\TextColumn::make('number')->label(__('Number'))->searchable()->weight('medium'),
                Tables\Columns\TextColumn::make('supplier_name')->label(__('Supplier'))->searchable()->limit(30),
                Tables\Columns\TextColumn::make('category')->label(__('Cost type'))->formatStateUsing(fn ($state) => static::categories()[$state] ?? $state)->toggleable(),
                Tables\Columns\TextColumn::make('booking.reference')->label(__('Booking'))->placeholder('—')
                    ->url(fn (SupplierInvoice $r) => $r->booking_id ? BookingResource::getUrl('edit', ['record' => $r->booking_id]) : null),
                Tables\Columns\TextColumn::make('costable_type')->label(__('Offer'))->toggleable()
                    ->state(fn (SupplierInvoice $r) => $r->costable ? (is_array($r->costable->getAttribute('title')) ? $r->costable->tr('title') : $r->costable->name) : null)->placeholder('—')->limit(30),
                Tables\Columns\TextColumn::make('amount')->label(__('Amount'))->alignEnd()->sortable()->formatStateUsing(fn (SupplierInvoice $r) => InvoiceResource::money($r->amount, $r->currency)),
                Tables\Columns\TextColumn::make('balance')->label(__('To pay'))->alignEnd()->state(fn (SupplierInvoice $r) => $r->balance())
                    ->formatStateUsing(fn ($state, SupplierInvoice $r) => InvoiceResource::money($state, $r->currency))->color(fn ($state) => $state > 0 ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('due_date')->label(__('Due date'))->date('d.m.Y')->sortable()
                    ->color(fn (SupplierInvoice $r) => $r->due_date?->isPast() && $r->balance() > 0 ? 'danger' : null),
                Tables\Columns\IconColumn::make('file')->label(__('Scan'))->boolean()->state(fn (SupplierInvoice $r) => (bool) $r->file)->toggleable(),
                AgencyField::column(),
            ])
            ->filters([
                Tables\Filters\Filter::make('unpaid')->label(__('Unpaid'))->query(fn (Builder $q) => $q->whereColumn('paid_amount', '<', 'amount')),
                Tables\Filters\Filter::make('overdue')->label(__('Overdue'))->query(fn (Builder $q) => $q->whereColumn('paid_amount', '<', 'amount')->whereDate('due_date', '<', today())),
                Tables\Filters\SelectFilter::make('category')->label(__('Cost type'))->options(static::categories()),
                Tables\Filters\SelectFilter::make('tour_operator_id')->label(__('Supplier'))->relationship('tourOperator', 'name')->searchable()->preload(),
                AgencyField::filter(),
            ])
            ->actions([
                static::payAction(),
                Tables\Actions\Action::make('file')->iconButton()->icon('heroicon-o-paper-clip')->tooltip(__('Open the scan'))
                    ->visible(fn (SupplierInvoice $r) => (bool) $r->file)->url(fn (SupplierInvoice $r) => \Storage::disk('public')->url($r->file), shouldOpenInNewTab: true),
                Tables\Actions\EditAction::make()->iconButton(),
            ])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()])
            ->paginated([25, 50, 100]);
    }

    public static function payAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('pay')->iconButton()->icon('heroicon-o-banknotes')->color('success')->tooltip(__('Pay (cash payout / bank)'))
            ->visible(fn (SupplierInvoice $r) => $r->balance() > 0)
            ->modalHeading(fn (SupplierInvoice $r) => __('Pay').' '.$r->supplier_name.' · '.$r->number)
            ->fillForm(fn (SupplierInvoice $r) => ['amount' => $r->balance(), 'date' => now()->toDateString(), 'method' => 'bank'])
            ->form(fn (SupplierInvoice $r) => [
                Forms\Components\TextInput::make('amount')->label(__('Amount'))->numeric()->required()->suffix($r->currency),
                Forms\Components\DatePicker::make('date')->label(__('Date'))->native(false)->displayFormat('d.m.Y')->required(),
                Forms\Components\Select::make('method')->label(__('Payment method'))->options(\App\Models\CashEntry::methods())->required()->live(),
                Forms\Components\Select::make('register')->label(__('Cash register'))->visible(fn (Get $get) => $get('method') !== 'bank')
                    ->options(fn () => collect(Finance::registers(Agency::find($r->agency_id)))->except('bank')->all())
                    ->default(fn () => Finance::registerFor('cash', Agency::find($r->agency_id))),
                Forms\Components\TextInput::make('note')->label(__('Note')),
            ])
            ->action(function (SupplierInvoice $r, array $data) {
                $e = Finance::paySupplier($r, (float) $data['amount'], $data['date'], $data['method'], $data['method'] === 'bank' ? 'bank' : ($data['register'] ?? null), $data['note'] ?? null);
                Notification::make()->success()->title(__('Payment registered'))->body($e->title())
                    ->actions([\Filament\Notifications\Actions\Action::make('print')->label(__('Print'))->url(\App\Services\Documents\Documents::url('cash', $e->id), shouldOpenInNewTab: true)])
                    ->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupplierInvoices::route('/'),
            'create' => Pages\CreateSupplierInvoice::route('/create'),
            'edit' => Pages\EditSupplierInvoice::route('/{record}/edit'),
        ];
    }
}

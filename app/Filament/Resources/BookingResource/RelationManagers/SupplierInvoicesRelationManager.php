<?php

namespace App\Filament\Resources\BookingResource\RelationManagers;

use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\SupplierInvoiceResource;
use App\Models\Booking;
use App\Models\SupplierInvoice;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/** Incoming invoices (supplier costs) of the booking – Влезна фактура */
class SupplierInvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'supplierInvoices';

    protected static ?string $icon = 'heroicon-o-inbox-arrow-down';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Incoming invoices');
    }

    public static function getModelLabel(): string
    {
        return mb_strtolower(__('Incoming invoice'));
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $u = Auth::user();

        return (bool) ($u?->isSuperAdmin() || $u?->can('finance.view'));
    }

    public function isReadOnly(): bool
    {
        $u = Auth::user();

        return ! ($u?->isSuperAdmin() || $u?->can('finance.manage'));
    }

    public function form(Form $form): Form
    {
        /** @var Booking $b */
        $b = $this->getOwnerRecord();

        return $form->columns(3)->schema(SupplierInvoiceResource::fields($b));
    }

    public function table(Table $table): Table
    {
        /** @var Booking $b */
        $b = $this->getOwnerRecord();

        return $table
            ->defaultSort('issue_date')
            ->description(function () use ($b) {
                $sum = $b->supplierInvoices()->get()->sum(fn ($i) => $b->fromCurrency((float) $i->amount, $i->currency, (float) $i->exchange_rate));

                return $sum ? __('Costs').': '.InvoiceResource::money($sum, $b->currency).' · '.__('Earnings').': '.InvoiceResource::money((float) $b->total - $sum, $b->currency) : null;
            })
            ->columns([
                Tables\Columns\TextColumn::make('issue_date')->label(__('Date'))->date('d.m.Y'),
                Tables\Columns\TextColumn::make('number')->label(__('Number')),
                Tables\Columns\TextColumn::make('supplier_name')->label(__('Supplier')),
                Tables\Columns\TextColumn::make('category')->label(__('Cost type'))->formatStateUsing(fn ($state) => SupplierInvoiceResource::categories()[$state] ?? $state),
                Tables\Columns\TextColumn::make('amount')->label(__('Amount'))->alignEnd()->formatStateUsing(fn (SupplierInvoice $r) => InvoiceResource::money($r->amount, $r->currency)),
                Tables\Columns\TextColumn::make('balance')->label(__('To pay'))->alignEnd()->state(fn (SupplierInvoice $r) => $r->balance())
                    ->formatStateUsing(fn ($state, SupplierInvoice $r) => InvoiceResource::money($state, $r->currency))->color(fn ($state) => $state > 0 ? 'danger' : 'success'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label(__('Add incoming invoice'))->icon('heroicon-o-plus')
                    ->mutateFormDataUsing(fn (array $data) => $data + ['agency_id' => $b->agency_id]),
            ])
            ->actions([
                SupplierInvoiceResource::payAction(),
                Tables\Actions\EditAction::make()->iconButton(),
                Tables\Actions\DeleteAction::make()->iconButton(),
            ])
            ->emptyStateHeading(__('No incoming invoices'))
            ->emptyStateDescription(__('Hotel, bus or tour-operator invoices for this booking – they become the real cost in the reports.'))
            ->paginated(false);
    }
}

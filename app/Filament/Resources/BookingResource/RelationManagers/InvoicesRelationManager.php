<?php

namespace App\Filament\Resources\BookingResource\RelationManagers;

use App\Filament\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\Documents\Documents;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/** Proformas and invoices issued for the booking */
class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    protected static ?string $icon = 'heroicon-o-document-currency-euro';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Invoices & proformas');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $u = Auth::user();

        return (bool) ($u?->isSuperAdmin() || $u?->can('finance.view'));
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('number')->label(__('Number'))->weight('medium'),
                Tables\Columns\TextColumn::make('type')->label(__('Type'))->badge()
                    ->formatStateUsing(fn ($state) => $state === 'proforma' ? __('Proforma') : __('Invoice'))->color(fn ($state) => $state === 'proforma' ? 'gray' : 'info'),
                Tables\Columns\TextColumn::make('issue_date')->label(__('Date'))->date('d.m.Y'),
                Tables\Columns\TextColumn::make('client_name')->label(__('Client')),
                Tables\Columns\TextColumn::make('total')->label(__('Total'))->alignEnd()->formatStateUsing(fn (Invoice $r) => InvoiceResource::money($r->total, $r->currency)),
                Tables\Columns\TextColumn::make('paid_amount')->label(__('Paid'))->alignEnd()->formatStateUsing(fn (Invoice $r) => InvoiceResource::money($r->paid_amount, $r->currency)),
                Tables\Columns\TextColumn::make('status')->label(__('Status'))->badge()->formatStateUsing(fn ($state) => Invoice::statuses()[$state] ?? $state)
                    ->color(fn ($state) => ['paid' => 'success', 'partial' => 'warning', 'cancelled' => 'danger', 'draft' => 'gray'][$state] ?? 'info'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('new')->label(__('New proforma / invoice'))->icon('heroicon-o-plus')
                    ->url(fn () => InvoiceResource::getUrl('create', ['booking' => $this->getOwnerRecord()->id, 'type' => 'proforma'])),
            ])
            ->actions([
                Tables\Actions\Action::make('print')->iconButton()->icon('heroicon-o-printer')->tooltip(__('Print'))
                    ->url(fn (Invoice $r) => Documents::url('invoice', $r->id), shouldOpenInNewTab: true),
                ...collect(InvoiceResource::recordActions(true))->map(fn ($a) => $a->after(fn () => $this->dispatch('refresh-booking')))->all(),
                Tables\Actions\Action::make('open')->iconButton()->icon('heroicon-o-pencil-square')->tooltip(__('Open'))
                    ->url(fn (Invoice $r) => InvoiceResource::getUrl('edit', ['record' => $r])),
            ])
            ->emptyStateHeading(__('No invoices yet'))
            ->paginated(false);
    }
}

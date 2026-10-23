<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Models\Invoice;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make('newProforma')->label(__('New proforma'))->color('gray')->url(InvoiceResource::getUrl('create', ['type' => 'proforma'])),
            Actions\CreateAction::make()->label(__('New invoice'))->url(InvoiceResource::getUrl('create', ['type' => 'invoice'])),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('All')),
            'invoice' => Tab::make(__('Invoices'))->modifyQueryUsing(fn (Builder $q) => $q->where('type', 'invoice')),
            'proforma' => Tab::make(__('Proformas'))->modifyQueryUsing(fn (Builder $q) => $q->where('type', 'proforma')),
            'unpaid' => Tab::make(__('Unpaid'))->badge(fn () => Invoice::where('type', 'invoice')->whereIn('status', ['issued', 'partial'])->count() ?: null)->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('type', 'invoice')->whereIn('status', ['issued', 'partial'])),
        ];
    }
}

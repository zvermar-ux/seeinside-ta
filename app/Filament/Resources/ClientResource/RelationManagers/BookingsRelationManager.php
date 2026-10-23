<?php

namespace App\Filament\Resources\ClientResource\RelationManagers;

use App\Filament\Resources\BookingResource;
use App\Models\Booking;
use App\Services\Reports\OfferReport;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Every booking where this person travelled (as holder or passenger) */
class BookingsRelationManager extends RelationManager
{
    protected static string $relationship = 'bookings';

    protected static ?string $icon = 'heroicon-o-clipboard-document-check';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Bookings');
    }

    public function table(Table $table): Table
    {
        $id = $this->getOwnerRecord()->getKey();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->orWhere(fn ($q) => $q->where('passengers', 'like', '%"client_id":'.$id.',%')->orWhere('passengers', 'like', '%"client_id":'.$id.'}%')
                ->orWhere('company_id', $id)))
            ->defaultSort('date_from', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('reference')->label(__('Reference'))->weight('medium'),
                Tables\Columns\TextColumn::make('offer')->label(__('Offer'))->state(fn (Booking $r) => $r->itemTitle())->wrap(),
                Tables\Columns\TextColumn::make('date_from')->label(__('Date'))->date('d.m.Y'),
                Tables\Columns\TextColumn::make('status')->label(__('Status'))->badge(),
                Tables\Columns\TextColumn::make('total')->label(__('Total'))->state(fn (Booking $r) => $r->total !== null ? OfferReport::money($r->total, $r->currency) : '—')->alignEnd(),
                Tables\Columns\TextColumn::make('owes')->label(__('Owes'))->state(fn (Booking $r) => OfferReport::money($r->balance(), $r->currency))->alignEnd()->color(fn (Booking $r) => $r->balance() > 0 ? 'danger' : 'gray'),
            ])
            ->recordUrl(fn (Booking $r) => BookingResource::getUrl('edit', ['record' => $r]))
            ->paginated([10, 25]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}

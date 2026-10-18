<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Reports;
use App\Filament\Resources\BookingResource;
use App\Models\Booking;
use App\Services\Reports\OfferReport;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

/** Clients who still owe money, earliest trip first – to call before they travel */
class DebtorsTable extends BaseWidget
{
    protected static ?int $sort = -2;

    protected int | string | array $columnSpan = ['md' => 2, 'xl' => 2];

    public static function canView(): bool
    {
        return Reports::canSee();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Clients who owe – by travel date'))
            ->query(fn () => Booking::query()->whereIn('bookings.status', OfferReport::DEFAULT_STATUSES)->whereColumn('bookings.paid_amount', '<', 'bookings.total')
                ->leftJoin('tour_departures', 'tour_departures.id', '=', 'bookings.tour_departure_id')
                ->select('bookings.*')->selectRaw('coalesce(bookings.date_from, tour_departures.start_date) as travel_on')
                ->with('bookable'))
            ->defaultSort(fn (Builder $query) => $query->orderByRaw('coalesce(bookings.date_from, tour_departures.start_date) is null')->orderByRaw('coalesce(bookings.date_from, tour_departures.start_date)'))
            ->columns([
                Tables\Columns\TextColumn::make('customer_name')->label(__('Customer'))->weight('medium')->description(fn (Booking $r) => $r->customer_phone)->searchable(),
                Tables\Columns\TextColumn::make('offer')->label(__('Offer'))->state(fn (Booking $r) => $r->itemTitle())->limit(32)->color('gray'),
                Tables\Columns\TextColumn::make('travel_on')->label(__('Travel'))->date('d.m.Y')
                    ->color(fn ($state) => $state && \Carbon\Carbon::parse($state)->lte(now()->addDays(7)) ? 'danger' : null),
                Tables\Columns\TextColumn::make('debt')->label(__('Owes'))->alignEnd()->weight('bold')->color('danger')
                    ->state(fn (Booking $r) => OfferReport::money($r->balance(), $r->currency)),
                Tables\Columns\TextColumn::make('payment_status')->label(__('Payment'))->badge(),
            ])
            ->recordUrl(fn (Booking $r) => BookingResource::getUrl('edit', ['record' => $r]))
            ->paginated([5, 10, 25])->defaultPaginationPageOption(5);
    }
}

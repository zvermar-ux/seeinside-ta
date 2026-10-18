<?php

namespace App\Filament\RelationManagers;

use App\Filament\Pages\Reports;
use App\Models\OfferCost;
use App\Models\TourOperator;
use App\Services\Reports\OfferReport;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/** Agency costs of an offer (several lines / rates) – the base of the financial report */
class CostsRelationManager extends RelationManager
{
    protected static string $relationship = 'costs';

    protected static ?string $icon = 'heroicon-o-calculator';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Reports::canSee();
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Costs').' ('.$ownerRecord->costs()->count().')';
    }

    public static function getModelLabel(): string
    {
        return mb_strtolower(__('Cost'));
    }

    public static function getPluralModelLabel(): string
    {
        return __('Costs');
    }

    public function isReadOnly(): bool
    {
        return ! Reports::canManage();
    }

    public function form(Form $form): Form
    {
        $owner = $this->getOwnerRecord();
        $type = $owner->getMorphClass();

        return $form->columns(6)->schema([
            Forms\Components\TextInput::make('name')->label(__('Cost item'))->required()->maxLength(190)->columnSpan(3)
                ->placeholder(__('e.g. Bus Skopje – Lefkada, Hotel 7 nights, Guide')),
            Forms\Components\Select::make('category')->label(__('Category'))->options(OfferCost::categories())->default('other')->required()->columnSpan(3),
            Forms\Components\Select::make('basis')->label(__('Calculated'))->options(OfferCost::bases())->default('per_person')->required()->live()->columnSpan(3)
                ->helperText(fn (Get $get) => match ($get('basis')) {
                    'fixed' => __('Paid once, regardless of the number of passengers.'),
                    'per_departure' => __('Once for every departure (date) that has bookings – e.g. bus rental.'),
                    'per_person_night', 'per_room_night' => __('Nights are taken from each booking (or from the tour duration).'),
                    'percent_revenue' => __('Amount = percent of the sales value (e.g. 10 = 10%).'),
                    default => __('Multiplied by the numbers from the bookings in the report.'),
                }),
            Forms\Components\TextInput::make('amount')->label(fn (Get $get) => $get('basis') === 'percent_revenue' ? __('Percent') : __('Price'))->numeric()->required()->columnSpan(2)
                ->suffix(fn (Get $get) => $get('basis') === 'percent_revenue' ? '%' : null),
            Forms\Components\Select::make('currency')->label(__('Currency'))->options(['EUR' => 'EUR', 'MKD' => 'MKD', 'USD' => 'USD'])->default('EUR')->columnSpan(1)
                ->hidden(fn (Get $get) => $get('basis') === 'percent_revenue'),
            Forms\Components\TextInput::make('quantity')->label(__('Multiplier'))->numeric()->default(1)->minValue(0)->columnSpan(2)
                ->helperText(__('e.g. 2 buses, 3 guides')),
            Forms\Components\Select::make('tour_departure_id')->label(__('Only for departure'))->columnSpan(2)
                ->options(fn () => $type === 'tour' ? $owner->departures()->orderBy('start_date')->get()->mapWithKeys(fn ($d) => [$d->id => $d->start_date?->format('d.m.Y').($d->end_date ? ' – '.$d->end_date->format('d.m.Y') : '')])->all() : [])
                ->placeholder(__('All departures'))->visible($type === 'tour'),
            Forms\Components\Select::make('accommodation_room_id')->label(__('Only for room'))->columnSpan(2)
                ->options(fn () => $type === 'accommodation' ? $owner->rooms()->get()->mapWithKeys(fn ($r) => [$r->id => is_array($r->name) ? ($r->name['mk'] ?? reset($r->name)) : $r->name])->all() : [])
                ->placeholder(__('All rooms'))->visible($type === 'accommodation'),
            Forms\Components\DatePicker::make('valid_from')->label(__('Valid from'))->native(false)->displayFormat('d.m.Y')->columnSpan(2)
                ->helperText(__('Seasonal rate: only bookings travelling in this period')),
            Forms\Components\DatePicker::make('valid_to')->label(__('Valid to'))->native(false)->displayFormat('d.m.Y')->columnSpan(2)->afterOrEqual('valid_from'),
            Forms\Components\Fieldset::make(__('Supplier'))->columns(4)->schema([
                Forms\Components\Select::make('tour_operator_id')->label(__('Tour operator'))->options(fn () => TourOperator::query()->orderBy('name')->pluck('name', 'id'))->searchable()->columnSpan(2),
                Forms\Components\TextInput::make('supplier')->label(__('Other supplier'))->maxLength(190)->columnSpan(2),
                Forms\Components\TextInput::make('paid_to_supplier')->label(__('Already paid to supplier'))->numeric()->default(0)->columnSpan(2),
                Forms\Components\DatePicker::make('due_date')->label(__('Payment due'))->native(false)->displayFormat('d.m.Y')->columnSpan(2),
            ]),
            Forms\Components\Textarea::make('note')->label(__('Note'))->rows(2)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        $owner = $this->getOwnerRecord();

        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->description(fn () => __('Enter what this offer costs the agency. The report multiplies each line by the bookings (passengers, nights, rooms) and shows profit and debts.'))
            ->columns([
                Tables\Columns\TextColumn::make('name')->label(__('Cost item'))->weight('medium')->wrap()
                    ->description(fn (OfferCost $r) => trim(implode(' · ', array_filter([
                        $r->departure ? __('Departure').' '.$r->departure->start_date?->format('d.m.Y') : null,
                        $r->room ? (is_array($r->room->name) ? ($r->room->name['mk'] ?? '') : $r->room->name) : null,
                        $r->valid_from || $r->valid_to ? ($r->valid_from?->format('d.m.Y') ?? '…').' – '.($r->valid_to?->format('d.m.Y') ?? '…') : null,
                    ])))),
                Tables\Columns\TextColumn::make('category')->label(__('Category'))->badge()->color('gray')->formatStateUsing(fn ($state) => OfferCost::categories()[$state] ?? $state),
                Tables\Columns\TextColumn::make('basis')->label(__('Calculated'))->formatStateUsing(fn ($state) => OfferCost::bases()[$state] ?? $state)->color('gray')->size('sm'),
                Tables\Columns\TextColumn::make('amount')->label(__('Price'))->alignEnd()
                    ->formatStateUsing(fn (OfferCost $r) => $r->basis === 'percent_revenue' ? rtrim(rtrim(number_format((float) $r->amount, 2, ',', '.'), '0'), ',').' %' : OfferReport::money($r->amount, $r->currency).((float) $r->quantity != 1 ? ' × '.rtrim(rtrim(number_format((float) $r->quantity, 2, ',', ''), '0'), ',') : '')),
                Tables\Columns\TextColumn::make('supplierName')->label(__('Supplier'))->state(fn (OfferCost $r) => $r->tourOperator?->name ?? $r->supplier)->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('paid_to_supplier')->label(__('Paid to supplier'))->alignEnd()->formatStateUsing(fn (OfferCost $r) => OfferReport::money($r->paid_to_supplier, $r->currency))->toggleable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label(__('Add cost'))->icon('heroicon-o-plus')->modalWidth('4xl'),
                Tables\Actions\Action::make('report')->label(__('Open report'))->icon('heroicon-o-chart-bar')->color('gray')
                    ->url(fn () => Reports::getUrl(['mode' => 'single', 'offer' => $owner->getMorphClass().':'.$owner->getKey()])),
            ])
            ->actions([
                Tables\Actions\ReplicateAction::make()->iconButton()->modalWidth('4xl')->form(fn (Form $form) => $this->form($form))
                    ->beforeReplicaSaved(fn (OfferCost $replica) => $replica->sort_order = (int) $owner->costs()->max('sort_order') + 1),
                Tables\Actions\EditAction::make()->iconButton()->modalWidth('4xl'),
                Tables\Actions\DeleteAction::make()->iconButton(),
            ])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()])
            ->emptyStateHeading(__('No costs yet'))
            ->emptyStateDescription(__('Add transport, accommodation, guides, tickets… as separate lines (rates).'))
            ->paginated(false);
    }
}

<?php

namespace App\Filament\Resources\AccommodationResource\RelationManagers;

use App\Enums\BookingStatus;
use App\Filament\Resources\AccommodationResource;
use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\StayTerm;
use App\Models\TermPrice;
use App\Services\Documents\Documents;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** Fixed booking terms (сезони / термини) of an accommodation: 15–22.05, 22–29.05 … */
class StayTermsRelationManager extends RelationManager
{
    protected static string $relationship = 'stayTerms';

    protected static ?string $icon = 'heroicon-o-calendar-days';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Terms').' ('.$ownerRecord->stayTerms()->count().')';
    }

    public static function getModelLabel(): ?string
    {
        return mb_strtolower(__('Term'));
    }

    public static function getPluralModelLabel(): ?string
    {
        return __('Terms');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form->columns(3)->schema([
            Forms\Components\DatePicker::make('date_from')->label(__('Check-in'))->native(false)->displayFormat('d.m.Y')->required()->live(),
            Forms\Components\DatePicker::make('date_to')->label(__('Check-out'))->native(false)->displayFormat('d.m.Y')->required()->after('date_from'),
            Forms\Components\Toggle::make('is_active')->label(__('Bookable'))->default(true)->inline(false),
            Forms\Components\TextInput::make('label')->label(__('Name (optional)'))->placeholder(__('e.g. 1st term')),
            Forms\Components\TextInput::make('note')->label(__('Note (flight no., bus…)'))->columnSpan(2),
        ]);
    }

    public function table(Table $table): Table
    {
        /** @var Accommodation $acc */
        $acc = $this->getOwnerRecord();
        $active = [BookingStatus::Pending->value, BookingStatus::Confirmed->value, BookingStatus::Completed->value];

        return $table
            ->defaultSort('date_from')
            ->modifyQueryUsing(fn ($query) => $query->with('prices')->withCount(['bookings' => fn ($q) => $q->withoutGlobalScopes()->whereIn('status', $active)]))
            ->description(fn () => $acc->book_by_terms ? null : __('Booking by terms is OFF – turn on “Booking only in fixed terms” in the “Prices & booking” tab so the website and the booking form use these terms.'))
            ->columns([
                Tables\Columns\TextColumn::make('date_from')->label(__('Check-in'))->date('D d.m.Y')->sortable(),
                Tables\Columns\TextColumn::make('date_to')->label(__('Check-out'))->date('D d.m.Y'),
                Tables\Columns\TextColumn::make('nights')->label(__('Nights'))->state(fn (StayTerm $r) => $r->nights())->alignCenter(),
                Tables\Columns\TextColumn::make('label')->label(__('Name'))->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('prices_info')->label(__('Prices'))
                    ->state(function (StayTerm $r) use ($acc) {
                        $p = $r->prices->whereNotNull('price')->where('stop_sale', false)->pluck('price')->map(fn ($v) => (float) $v);
                        if ($p->isEmpty()) {
                            return __('no prices');
                        }

                        return ($p->min() === $p->max() ? number_format($p->min(), 0, ',', '.') : number_format($p->min(), 0, ',', '.').' – '.number_format($p->max(), 0, ',', '.')).' '.$acc->currency;
                    })
                    ->color(fn ($state) => $state === __('no prices') ? 'danger' : null),
                Tables\Columns\TextColumn::make('stop')->label(__('Stop sale'))->state(fn (StayTerm $r) => $r->prices->where('stop_sale', true)->count() ?: null)->badge()->color('danger')->placeholder(''),
                Tables\Columns\TextColumn::make('bookings_count')->label(__('Bookings'))->badge()->color(fn ($state) => $state ? 'success' : 'gray'),
                Tables\Columns\IconColumn::make('is_active')->label(__('Bookable'))->boolean(),
                Tables\Columns\TextColumn::make('note')->label(__('Note'))->limit(30)->placeholder('')->toggleable(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('generate')->label(__('Generate terms'))->icon('heroicon-o-sparkles')
                    ->modalDescription(__('Example: start 15.05, 7 nights, until 30.09 → 15–22.05, 22–29.05, 29.05–05.06 …'))
                    ->form([
                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\DatePicker::make('start')->label(__('First check-in'))->native(false)->displayFormat('d.m.Y')->required()->live(),
                            Forms\Components\TextInput::make('nights')->label(__('Nights per term'))->numeric()->minValue(1)->default(7)->required()->live(),
                            Forms\Components\Select::make('gap')->label(__('Next term starts'))->selectablePlaceholder(false)->default(0)
                                ->options([0 => __('on the check-out day (15–22, 22–29…)'), 1 => __('the day after (15–22, 23–30…)')]),
                            Forms\Components\DatePicker::make('until')->label(__('Last check-out by'))->native(false)->displayFormat('d.m.Y')->live()
                                ->helperText(__('…or enter the number of terms')),
                            Forms\Components\TextInput::make('count')->label(__('Number of terms'))->numeric()->minValue(1)->maxValue(100)->live(),
                            Forms\Components\TextInput::make('label_prefix')->label(__('Name prefix (optional)'))->placeholder(__('Term')),
                        ]),
                        Forms\Components\Placeholder::make('preview')->label(__('Preview'))
                            ->content(fn (Get $get) => collect(static::plan($get('start'), (int) $get('nights'), (int) $get('gap'), $get('until'), (int) $get('count')))
                                ->map(fn ($t) => $t[0]->format('d.m').'–'.$t[1]->format('d.m.Y'))->take(40)->implode(' · ') ?: '—'),
                        Forms\Components\Toggle::make('copy_prices')->label(__('Copy room prices from the last existing term'))->default(true),
                    ])
                    ->action(function (array $data) use ($acc) {
                        $plan = static::plan($data['start'], (int) $data['nights'], (int) ($data['gap'] ?? 0), $data['until'] ?? null, (int) ($data['count'] ?? 0));
                        if (! $plan) {
                            Notification::make()->warning()->title(__('Enter the end date or the number of terms.'))->send();

                            return;
                        }
                        $template = $data['copy_prices'] ? $acc->stayTerms()->with('prices')->reorder('date_from', 'desc')->first() : null;
                        $made = 0;
                        TermPrice::$quiet = true;
                        try {
                            DB::transaction(function () use ($plan, $acc, $data, $template, &$made) {
                                foreach ($plan as $i => [$from, $to]) {
                                    if ($acc->stayTerms()->whereDate('date_from', $from)->whereDate('date_to', $to)->exists()) {
                                        continue;
                                    }
                                    $t = $acc->stayTerms()->create(['date_from' => $from, 'date_to' => $to, 'is_active' => true,
                                        'label' => filled($data['label_prefix'] ?? null) ? $data['label_prefix'].' '.($i + 1) : null]);
                                    foreach ($template?->prices ?? [] as $p) {
                                        $t->prices()->create(['accommodation_room_id' => $p->accommodation_room_id, 'price' => $p->price, 'cost_price' => $p->cost_price]);
                                    }
                                    $made++;
                                }
                            });
                        } finally {
                            TermPrice::$quiet = false;
                        }
                        $acc->touch();
                        Notification::make()->success()->title(__(':n terms created', ['n' => $made]))->send();
                    }),
                Tables\Actions\Action::make('prices')->label(__('Price matrix'))->icon('heroicon-o-table-cells')->color('gray')
                    ->url(fn () => AccommodationResource::getUrl('term-prices', ['record' => $acc])),
                Tables\Actions\CreateAction::make()->label(__('Add term'))->color('gray'),
            ])
            ->actions([
                Tables\Actions\Action::make('rooming')->iconButton()->icon('heroicon-o-clipboard-document-list')->tooltip(__('Rooming list'))
                    ->url(fn (StayTerm $r) => Documents::url('rooming', $r->id, ['kind' => 'term']), shouldOpenInNewTab: true),
                Tables\Actions\EditAction::make()->iconButton(),
                Tables\Actions\DeleteAction::make()->iconButton()->visible(fn (StayTerm $r) => ! $r->bookings_count),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('on')->label(__('Bookable'))->icon('heroicon-o-check')->action(fn (Collection $r) => $r->each->update(['is_active' => true])),
                Tables\Actions\BulkAction::make('off')->label(__('Not bookable'))->icon('heroicon-o-x-mark')->action(fn (Collection $r) => $r->each->update(['is_active' => false])),
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->emptyStateHeading(__('No terms yet'))
            ->emptyStateDescription(__('Generate the season (e.g. every 7 nights from 15.05) and enter prices per room in the price matrix.'))
            ->paginated([25, 50, 100]);
    }

    /** @return array<int, array{0: Carbon, 1: Carbon}> */
    public static function plan(?string $start, int $nights, int $gap, ?string $until, int $count): array
    {
        if (! $start || $nights < 1 || (! $until && $count < 1)) {
            return [];
        }
        $out = [];
        $from = Carbon::parse($start)->startOfDay();
        $end = $until ? Carbon::parse($until)->startOfDay() : null;
        while (count($out) < min(100, $count ?: 100)) {
            $to = $from->copy()->addDays($nights);
            if ($end && $to->gt($end)) {
                break;
            }
            $out[] = [$from->copy(), $to];
            $from = $to->copy()->addDays($gap);
        }

        return $out;
    }
}

<?php

namespace App\Filament\Pages;

use App\Enums\AccommodationType;
use App\Enums\BookingStatus;
use App\Enums\EventCategory;
use App\Enums\TourType;
use App\Models\Group;
use App\Models\TourOperator;
use App\Services\Reports\GroupReport;
use App\Services\Reports\OfferReport;
use App\Services\Reports\PeriodReport;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Financial and availability reports: one offer (tour / hotel / event…) or a group of offers */
class Reports extends Page implements HasForms
{
    use InteractsWithForms, \App\Filament\Concerns\CustomizableNavigation;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'bookings';

    protected static ?int $navigationSort = 0;

    protected static ?string $slug = 'reports';

    protected static string $view = 'filament.pages.reports';

    public ?array $filters = [];

    /** what the report was built with (so editing the filters does not recalculate on every keystroke) */
    public array $applied = [];

    public static function canSee(): bool
    {
        $u = Auth::user();

        return (bool) ($u && ($u->isSuperAdmin() || $u->can('reports.view')));
    }

    public static function canManage(): bool
    {
        $u = Auth::user();

        return (bool) ($u && ($u->isSuperAdmin() || $u->can('reports.manage')));
    }

    public static function canAccess(): bool
    {
        if (! \App\Models\Agency::moduleOn('reports')) {
            return false;
        }
        return static::canSee();
    }

    public static function defaultNavigationLabel(): string
    {
        return __('Reports');
    }

    public function getTitle(): string
    {
        return __('Reports');
    }

    public function mount(): void
    {
        $q = request()->query();
        $this->form->fill([
            'mode' => in_array($q['mode'] ?? null, ['single', 'group', 'period'], true) ? $q['mode'] : 'period',
            'period' => in_array($q['period'] ?? null, ['year', 'month', 'week', 'range'], true) ? $q['period'] : 'month',
            'year' => (int) ($q['year'] ?? now()->year), 'month' => (int) ($q['month'] ?? now()->month), 'week' => now()->toDateString(),
            'basis' => 'travel', 'compare' => true,
            'offer' => $q['offer'] ?? null,
            'departure_id' => $q['departure'] ?? null,
            'from' => $q['from'] ?? null, 'to' => $q['to'] ?? null,
            'statuses' => OfferReport::DEFAULT_STATUSES,
            'types' => [], 'groups' => [], 'accommodation_types' => [], 'tour_types' => [], 'event_categories' => [],
            'place' => $q['place'] ?? null, 'tour_operator_id' => null, 'only_with_bookings' => true,
        ]);
        if (($q['offer'] ?? null) || in_array($q['mode'] ?? 'period', ['group', 'period'], true)) {
            $this->run();
        }
    }

    public function form(Form $form): Form
    {
        return $form->statePath('filters')->schema([
            Forms\Components\ToggleButtons::make('mode')->hiddenLabel()->inline()->live()
                ->options(['period' => __('Agency – year / month / week'), 'single' => __('Single offer'), 'group' => __('Group report')])
                ->icons(['period' => 'heroicon-o-calendar', 'single' => 'heroicon-o-document-chart-bar', 'group' => 'heroicon-o-squares-2x2'])
                ->afterStateUpdated(fn () => $this->applied = []),
            Forms\Components\Grid::make(['default' => 1, 'md' => 2, 'xl' => 4])->schema([
                Forms\Components\ToggleButtons::make('period')->label(__('Period'))->inline()->live()->columnSpan(['md' => 2])
                    ->options(['year' => __('Year'), 'month' => __('Month'), 'week' => __('Week'), 'range' => __('From – to')])
                    ->visible(fn (Get $get) => $get('mode') === 'period'),
                Forms\Components\Select::make('year')->label(__('Year'))->selectablePlaceholder(false)
                    ->options(collect(range(now()->year + 1, now()->year - 6))->mapWithKeys(fn ($y) => [$y => $y])->all())
                    ->visible(fn (Get $get) => $get('mode') === 'period' && in_array($get('period'), ['year', 'month'], true)),
                Forms\Components\Select::make('month')->label(__('Month'))->selectablePlaceholder(false)
                    ->options(collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => mb_convert_case(\Carbon\Carbon::create(2000, $m, 1)->locale(app()->getLocale())->isoFormat('MMMM'), MB_CASE_TITLE)])->all())
                    ->visible(fn (Get $get) => $get('mode') === 'period' && $get('period') === 'month'),
                Forms\Components\DatePicker::make('week')->label(__('Any day of the week'))->native(false)->displayFormat('d.m.Y')
                    ->visible(fn (Get $get) => $get('mode') === 'period' && $get('period') === 'week'),
                Forms\Components\Select::make('basis')->label(__('Count bookings by'))->selectablePlaceholder(false)
                    ->options(['travel' => __('Travel date (when the trip happens)'), 'booked' => __('Booking date (when it was sold)')])
                    ->visible(fn (Get $get) => $get('mode') === 'period'),
                Forms\Components\Select::make('offer')->label(__('Offer'))->searchable()->live()->columnSpan(['md' => 2])
                    ->options(fn () => $this->offerOptions())
                    ->afterStateUpdated(fn (Forms\Set $set) => $set('departure_id', null))
                    ->visible(fn (Get $get) => $get('mode') === 'single')->required(fn (Get $get) => $get('mode') === 'single'),
                Forms\Components\Select::make('departure_id')->label(__('Departure'))->placeholder(__('All departures'))
                    ->options(function (Get $get) {
                        $o = OfferReport::find($get('offer'));

                        return $o instanceof \App\Models\Tour ? $o->departures()->orderBy('start_date')->get()
                            ->mapWithKeys(fn ($d) => [$d->id => $d->start_date?->format('d.m.Y').($d->end_date ? ' – '.$d->end_date->format('d.m.Y') : '')])->all() : [];
                    })
                    ->visible(fn (Get $get) => $get('mode') === 'single' && str_starts_with((string) $get('offer'), 'tour:')),

                Forms\Components\CheckboxList::make('types')->label(__('Offer types'))->columnSpan(['md' => 2])->columns(3)
                    ->options(OfferReport::typeLabels())->helperText(__('None checked = all types'))
                    ->visible(fn (Get $get) => in_array($get('mode'), ['group', 'period'], true)),
                Forms\Components\Select::make('groups')->label(__('Groups & destinations'))->multiple()->searchable()->columnSpan(['md' => 2])
                    ->options(fn () => Group::query()->get()->sortBy('display_name')->mapWithKeys(fn (Group $g) => [$g->id => $g->display_name])->all())
                    ->helperText(__('Sub-destinations are included automatically'))
                    ->visible(fn (Get $get) => $get('mode') === 'group'),
                Forms\Components\Select::make('accommodation_types')->label(__('Accommodation types'))->multiple()->options(AccommodationType::class)
                    ->visible(fn (Get $get) => $get('mode') === 'group'),
                Forms\Components\Select::make('tour_types')->label(__('Tour types'))->multiple()->options(TourType::class)
                    ->visible(fn (Get $get) => $get('mode') === 'group'),
                Forms\Components\Select::make('event_categories')->label(__('Event categories'))->multiple()->options(EventCategory::class)
                    ->visible(fn (Get $get) => $get('mode') === 'group'),
                Forms\Components\TextInput::make('place')->label(__('Place / name contains'))->placeholder(__('e.g. Lefkada'))
                    ->visible(fn (Get $get) => $get('mode') === 'group'),
                Forms\Components\Select::make('tour_operator_id')->label(__('Tour operator'))->options(fn () => TourOperator::query()->orderBy('name')->pluck('name', 'id'))->searchable()
                    ->visible(fn (Get $get) => $get('mode') === 'group'),

                Forms\Components\DatePicker::make('from')->label(fn (Get $get) => $get('mode') === 'period' ? __('From') : __('Travel from'))->native(false)->displayFormat('d.m.Y')
                    ->visible(fn (Get $get) => $get('mode') !== 'period' || $get('period') === 'range'),
                Forms\Components\DatePicker::make('to')->label(fn (Get $get) => $get('mode') === 'period' ? __('To') : __('Travel to'))->native(false)->displayFormat('d.m.Y')->afterOrEqual('from')
                    ->visible(fn (Get $get) => $get('mode') !== 'period' || $get('period') === 'range'),
                Forms\Components\CheckboxList::make('statuses')->label(__('Bookings counted'))->columnSpan(['md' => 2])->columns(2)->required()
                    ->options(collect(BookingStatus::cases())->reject(fn ($s) => $s === BookingStatus::Cancelled)->mapWithKeys(fn ($s) => [$s->value => $s->getLabel()])->all())
                    ->helperText(__('Cancelled bookings are always shown separately (money to refund).')),
                Forms\Components\Toggle::make('only_with_bookings')->label(__('Only offers with bookings'))->inline(false)
                    ->visible(fn (Get $get) => $get('mode') === 'group'),
            ]),
        ]);
    }

    protected function offerOptions(): array
    {
        $out = [];
        foreach (OfferReport::TYPES as $type => $class) {
            $label = OfferReport::typeLabels()[$type];
            foreach ($class::query()->latest('id')->limit(500)->get() as $m) {
                $title = OfferReport::title($m);
                $extra = $m->getAttribute('place') ?? $m->getAttribute('location') ?? $m->getAttribute('city');
                $out[$label][$type.':'.$m->getKey()] = $title.($extra && ! str_contains($title, (string) $extra) ? ' — '.$extra : '');
            }
        }

        return $out;
    }

    public function run(): void
    {
        $data = $this->form->getState();
        $data['statuses'] = array_values($data['statuses'] ?: OfferReport::DEFAULT_STATUSES);
        $this->applied = $data;
    }

    public function scopeFilters(): array
    {
        $a = $this->applied;

        return ['departure_id' => $a['departure_id'] ?? null, 'from' => $a['from'] ?? null, 'to' => $a['to'] ?? null, 'statuses' => $a['statuses'] ?? OfferReport::DEFAULT_STATUSES];
    }

    public function period(): ?PeriodReport
    {
        if (($this->applied['mode'] ?? null) !== 'period') {
            return null;
        }

        return new PeriodReport($this->applied);
    }

    public function single(): ?OfferReport
    {
        if (($this->applied['mode'] ?? null) !== 'single') {
            return null;
        }
        $o = OfferReport::find($this->applied['offer'] ?? null);
        if (! $o) {
            return null;
        }
        $f = $this->scopeFilters();
        if (! $f['from'] && ! $f['to'] && in_array($o->getAttribute('ownership'), ['lease', 'own'], true)) {
            $f = OfferReport::leaseWindow($o, $f);
            $f['from'] = OfferReport::leaseWindow($o)['from'];
            $f['to'] = OfferReport::leaseWindow($o)['to'];
        }

        return new OfferReport($o, $f);
    }

    public function group(): ?GroupReport
    {
        if (($this->applied['mode'] ?? null) !== 'group') {
            return null;
        }

        return new GroupReport($this->applied);
    }

    /** Open a single report from a row of the group report */
    public function openOffer(string $key, ?string $from = null, ?string $to = null): void
    {
        $this->form->fill(array_merge($this->filters, ['mode' => 'single', 'offer' => $key, 'departure_id' => null], $from ? ['from' => $from, 'to' => $to] : []));
        $this->run();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')->label(__('Print / PDF'))->icon('heroicon-o-printer')->color('gray')
                ->visible(fn () => (bool) $this->applied)
                ->alpineClickHandler('window.print()'),
            Action::make('csv')->label(__('Export to Excel (CSV)'))->icon('heroicon-o-arrow-down-tray')->color('gray')
                ->visible(fn () => (bool) $this->applied)
                ->action(fn () => $this->exportCsv()),
        ];
    }

    public function exportCsv(): ?StreamedResponse
    {
        $rows = [];
        $n = fn ($v) => $v === null ? '' : str_replace('.', ',', (string) round((float) $v, 2));
        if ($r = $this->single()) {
            $t = $r->totals($lines = $r->costLines());
            $cur = $r->currency();
            $rows[] = [__('Report'), OfferReport::title($r->offer)];
            $rows[] = [__('Period'), trim(($this->applied['from'] ?? '').' – '.($this->applied['to'] ?? ''), ' –')];
            $rows[] = [];
            foreach ([__('Sales') => 'revenue', __('Collected') => 'collected', __('Clients owe') => 'owed', __('Cost') => 'cost', __('Paid to suppliers') => 'cost_paid', __('Owed to suppliers') => 'cost_owed', __('Profit') => 'profit'] as $l => $k) {
                $rows[] = [$l, $n($t[$k]), $cur];
            }
            $rows[] = [__('Bookings'), $t['bookings']];
            $rows[] = [__('Passengers'), $t['pax']];
            $rows[] = [];
            $rows[] = [__('Costs')];
            $rows[] = [__('Cost item'), __('Category'), __('Calculated'), __('Units'), __('Price'), __('Multiplier'), __('Total'), __('Paid to supplier'), __('Owed to supplier'), __('Supplier'), __('Currency')];
            foreach ($lines as $l) {
                $rows[] = [$l['name'], $l['category'], $l['basis'], $n($l['units']).' '.$l['unit_label'], $n($l['rate']), $n($l['quantity']), $n($l['total']), $n($l['paid']), $n($l['owed']), $l['supplier'], $l['currency']];
            }
            $rows[] = [];
            $rows[] = [__('Passengers')];
            $rows[] = [__('Reference'), __('Customer'), __('Phone'), __('E-mail'), __('Passengers'), __('Adults'), __('Children'), __('Date'), __('Room / ticket'), __('Status'), __('Total'), __('Paid'), __('Owes'), __('Payment'), __('Currency')];
            foreach ($r->clients() as $c) {
                $rows[] = [$c['reference'], $c['customer'], $c['phone'], $c['email'], implode(', ', $c['passengers']), $c['adults'], $c['children'], $c['date'], $c['room'], $c['status'], $n($c['total']), $n($c['paid']), $n($c['owed']), OfferReport::payStateLabels()[$c['state']] ?? $c['state'], $c['currency']];
            }
            $name = 'izvestaj-'.\Illuminate\Support\Str::slug(OfferReport::title($r->offer)).'.csv';
        } elseif ($pr = $this->period()) {
            $d = $pr->build();
            $rows[] = [__('Report'), $pr->title()];
            $rows[] = [];
            $rows[] = [__('Period'), __('Bookings'), __('Passengers'), __('Sales'), __('Cost'), __('Profit'), __('Margin %'), __('Collected'), __('Clients owe'), __('Payments received'), __('Refunds')];
            foreach (array_merge($d['series'], [['label' => __('Total')] + $d['total'] + ['pay' => $d['pay']]]) as $x) {
                $rows[] = [$x['label'], $x['bookings'], $x['pax'], $n($x['revenue']), $n($x['cost']), $n($x['profit']), $n($x['margin']), $n($x['collected']), $n($x['owed']), $n($x['pay']['in']), $n($x['pay']['out'])];
            }
            foreach ([__('By offer type') => $d['byType'], __('By tour operator') => $d['byOperator'], __('By employee') => $d['byEmployee']] as $title => $list) {
                $rows[] = [];
                $rows[] = [$title];
                $rows[] = ['', __('Bookings'), __('Passengers'), __('Sales'), __('Cost'), __('Profit'), __('Margin %'), __('Collected'), __('Clients owe')];
                foreach ($list as $x) {
                    $rows[] = [$x['label'], $x['bookings'], $x['pax'], $n($x['revenue']), $n($x['cost']), $n($x['profit']), $n($x['margin']), $n($x['collected']), $n($x['owed'])];
                }
            }
            $rows[] = [];
            $rows[] = [__('Offers')];
            $rows[] = [__('Offer'), __('Bookings'), __('Passengers'), __('Sales'), __('Cost'), __('Profit'), __('Margin %'), __('Collected'), __('Clients owe')];
            foreach ($d['top'] as $x) {
                $rows[] = [$x['title'], $x['bookings'], $x['pax'], $n($x['revenue']), $n($x['cost']), $n($x['profit']), $n($x['margin']), $n($x['collected']), $n($x['owed'])];
            }
            $name = 'izvestaj-'.\Illuminate\Support\Str::slug($pr->title()).'.csv';
        } elseif ($g = $this->group()) {
            $data = $g->rows();
            $rows[] = [__('Offer'), __('Type'), __('Place'), __('Bookings'), __('Passengers'), __('Sales'), __('Collected'), __('Clients owe'), __('Cost'), __('Owed to suppliers'), __('Profit'), __('Margin %'), __('Occupancy %'), __('Paid in full'), __('Partly paid'), __('Not paid'), __('Currency')];
            foreach (array_merge($data, [['title' => __('Total')] + GroupReport::sum($data) + ['type' => '', 'place' => '', 'occupancy' => null]]) as $x) {
                $rows[] = [$x['title'], $x['type'], $x['place'], $x['bookings'], $x['pax'], $n($x['revenue']), $n($x['collected']), $n($x['owed']), $n($x['cost']), $n($x['cost_owed']), $n($x['profit']), $n($x['margin']), $x['occupancy'], $x['paid_count'], $x['partial_count'], $x['unpaid_count'], $x['currency']];
            }
            $name = 'grupen-izvestaj-'.now()->format('Y-m-d').'.csv';
        } else {
            return null;
        }

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");   // UTF-8 BOM so Excel shows Cyrillic
            foreach ($rows as $row) {
                fputcsv($out, $row, ';');
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}

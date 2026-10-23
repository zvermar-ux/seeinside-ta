<?php

namespace App\Filament\Resources\AccommodationResource\Pages;

use App\Filament\Resources\AccommodationResource;
use App\Models\Accommodation;
use App\Models\TermPrice;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;

/** Price matrix of the fixed terms: rooms × terms, sale and purchase prices, stop-sale */
class TermPrices extends Page
{
    use InteractsWithRecord;

    protected static string $resource = AccommodationResource::class;

    protected static string $view = 'filament.resources.accommodation-resource.pages.term-prices';

    /** [termId][roomId] => ['price' => , 'cost_price' => , 'stop_sale' => ] */
    public array $cells = [];

    public string $mode = 'price';

    public bool $past = false;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        abort_unless(AccommodationResource::canEdit($this->record), 403);
        $this->loadCells();
    }

    public function getTitle(): string
    {
        return __('Price matrix').' – '.$this->record->name;
    }

    public function getBreadcrumbs(): array
    {
        return [
            AccommodationResource::getUrl() => AccommodationResource::getPluralModelLabel(),
            AccommodationResource::getUrl('edit', ['record' => $this->record]) => $this->record->name,
            __('Price matrix'),
        ];
    }

    protected function terms()
    {
        /** @var Accommodation $a */
        $a = $this->record;

        return $a->stayTerms()->when(! $this->past, fn ($q) => $q->whereDate('date_to', '>=', today()))->with('prices')->get();
    }

    public function updatedPast(): void
    {
        $this->loadCells();
    }

    protected function loadCells(): void
    {
        $rooms = $this->record->rooms()->pluck('id');
        $this->cells = [];
        foreach ($this->terms() as $t) {
            foreach ($rooms as $rid) {
                $p = $t->prices->firstWhere('accommodation_room_id', $rid);
                $this->cells[$t->id][$rid] = [
                    'price' => $p?->price !== null ? (float) $p->price : null,
                    'cost_price' => $p?->cost_price !== null ? (float) $p->cost_price : null,
                    'stop_sale' => (bool) $p?->stop_sale,
                ];
            }
        }
    }

    /** same value for a whole room column (only empty cells unless $overwrite) */
    public function fillColumn(int $roomId, $value, bool $overwrite = false): void
    {
        if ($value === null || $value === '') {
            return;
        }
        foreach ($this->cells as $tid => $row) {
            if (array_key_exists($roomId, $row) && ($overwrite || $row[$roomId][$this->mode] === null)) {
                $this->cells[$tid][$roomId][$this->mode] = (float) $value;
            }
        }
    }

    /** sale price = purchase price + markup % (all cells with a purchase price) */
    public function applyMarkup($percent): void
    {
        $percent = (float) $percent;
        foreach ($this->cells as $tid => $row) {
            foreach ($row as $rid => $c) {
                if ($c['cost_price'] !== null && $c['cost_price'] !== '') {
                    $this->cells[$tid][$rid]['price'] = round((float) $c['cost_price'] * (1 + $percent / 100), 0);
                }
            }
        }
        $this->mode = 'price';
    }

    public function save(): void
    {
        $termIds = $this->record->stayTerms()->pluck('id')->all();
        $roomIds = $this->record->rooms()->pluck('id')->all();
        TermPrice::$quiet = true;
        try {
            DB::transaction(function () use ($termIds, $roomIds) {
                foreach ($this->cells as $tid => $row) {
                    if (! in_array((int) $tid, $termIds)) {
                        continue;
                    }
                    foreach ($row as $rid => $c) {
                        if (! in_array((int) $rid, $roomIds)) {
                            continue;
                        }
                        $num = fn ($v) => ($v === null || $v === '') ? null : (float) $v;
                        $vals = ['price' => $num($c['price'] ?? null), 'cost_price' => $num($c['cost_price'] ?? null), 'stop_sale' => (bool) ($c['stop_sale'] ?? false)];
                        $existing = TermPrice::where('stay_term_id', $tid)->where('accommodation_room_id', $rid)->first();
                        if ($vals['price'] === null && $vals['cost_price'] === null && ! $vals['stop_sale']) {
                            $existing?->delete();

                            continue;
                        }
                        $existing ? $existing->update($vals) : TermPrice::create($vals + ['stay_term_id' => $tid, 'accommodation_room_id' => $rid]);
                    }
                }
            });
        } finally {
            TermPrice::$quiet = false;
        }
        $this->record->touch();
        Notification::make()->success()->title(__('Saved'))->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')->label(__('Back'))->color('gray')->icon('heroicon-o-arrow-left')
                ->url(AccommodationResource::getUrl('edit', ['record' => $this->record, 'activeRelationManager' => 1])),
            Actions\Action::make('save')->label(__('Save prices'))->action('save')->keyBindings(['mod+s']),
        ];
    }

    protected function getViewData(): array
    {
        return ['terms' => $this->terms(), 'rooms' => $this->record->rooms()->get()];
    }
}

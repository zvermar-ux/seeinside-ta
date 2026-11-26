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
                        // a price changed here by hand is kept by the sync with the old system
                        if (! $existing || (float) $existing->price !== (float) $vals['price']) {
                            $vals['price_manual'] = $vals['price'] !== null;
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
            $this->pasteAction(),
            Actions\Action::make('save')->label(__('Save prices'))->action('save')->keyBindings(['mod+s']),
        ];
    }

    /* ---------------- price list pasted from Excel / Word / PDF ---------------- */

    /** "22.05-29.05.2027 229 249 269" → [[from, to], [229, 249, 269]] */
    public static function parseList(string $text, int $year): array
    {
        $out = [];
        foreach (preg_split('/\r?\n/', $text) as $line) {
            if (! preg_match('/(\d{1,2})\.(\d{1,2})\.?(\d{4})?\s*[-–—]\s*(\d{1,2})\.(\d{1,2})\.?(\d{4})?(.*)$/u', trim($line), $m)) {
                continue;
            }
            $y1 = $m[3] !== '' ? (int) $m[3] : ($m[6] !== '' ? (int) $m[6] : $year);
            $y2 = $m[6] !== '' ? (int) $m[6] : $y1;
            if ($m[6] === '' && ((int) $m[5] < (int) $m[2])) {
                $y2 = $y1 + 1;   // 28.12–04.01
            }
            preg_match_all('/\d+(?:[.,]\d{1,2})?/', $m[7], $n);
            $from = sprintf('%04d-%02d-%02d', $y1, $m[2], $m[1]);
            $to = sprintf('%04d-%02d-%02d', $y2, $m[5], $m[4]);
            if (checkdate((int) $m[2], (int) $m[1], $y1) && checkdate((int) $m[5], (int) $m[4], $y2) && $to > $from) {
                $out[] = [[$from, $to], array_map(fn ($v) => (float) str_replace(',', '.', $v), $n[0])];
            }
        }

        return $out;
    }

    /** the agency's price list of this apartment (resources/data), when there is one */
    protected function knownList(): ?array
    {
        $file = resource_path('data/pricelists-2027.php');
        if (! is_file($file)) {
            return null;
        }
        $alias = ['akropolis' => ['akropolis', 'акрополис'], 'bellagio' => ['bellagio', 'белаџ', 'белађ'], 'armonia' => ['armonia', 'армони'], 'nidri port' => ['nidri port', 'нидри порт'], 'afrodit' => ['afrodit', 'афродит']];
        $name = mb_strtolower(json_encode($this->record->getRawOriginal('name'), JSON_UNESCAPED_UNICODE));
        foreach (require $file as $key => $pl) {
            foreach ($alias[$key] ?? [$key] as $w) {
                if (str_contains($name, $w)) {
                    return $pl;
                }
            }
        }

        return null;
    }

    protected function pasteAction(): Actions\Action
    {
        $rooms = fn () => $this->record->rooms()->get()->mapWithKeys(fn ($r) => [$r->id => $r->tr('name')])->all();

        return Actions\Action::make('paste')->label(__('Paste price list'))->icon('heroicon-o-clipboard-document-list')->color('info')
            ->modalHeading(__('Paste price list'))->modalWidth('5xl')
            ->modalDescription(__('Copy the table of the price list (term + one price per room in each row, e.g. "22.05-29.05.2027 229 249 269 289") and choose which room each price column is. Missing terms are created; stop sale stays as it is.'))
            ->fillForm(function () use ($rooms) {
                $pl = $this->knownList();
                $data = ['year' => (int) ($pl['year'] ?? now()->addYear()->year), 'manual' => true, 'table' => ''];
                if ($pl) {
                    $data['table'] = collect($pl['terms'])->map(fn ($row, $k) => $k.' '.implode(' ', $row))->implode("\n");
                    // each column to the room with the most similar name
                    $free = $rooms();
                    foreach ($pl['cols'] as $i => $col) {
                        $best = null;
                        $score = 0;
                        foreach ($free as $id => $name) {
                            similar_text(mb_strtolower(\App\Services\Mcs\McsService::latin((string) $name)), mb_strtolower(\App\Services\Mcs\McsService::latin($col)), $pct);
                            if ($pct > $score) {
                                [$best, $score] = [$id, $pct];
                            }
                        }
                        if ($best && $score >= 55) {
                            $data['col_'.$i] = $best;
                            unset($free[$best]);
                        }
                    }
                }

                return $data;
            })
            ->form(function () use ($rooms) {
                $pl = $this->knownList();
                $fields = [
                    \Filament\Forms\Components\Textarea::make('table')->label(__('Price list'))->rows(12)->required()->live(debounce: 600)
                        ->helperText($pl ? __('Filled from the price list :t – check it.', ['t' => $pl['title']]) : null),
                    \Filament\Forms\Components\TextInput::make('year')->label(__('Year (when the dates have none)'))->numeric()->required(),
                ];
                $cols = [];
                for ($i = 0; $i < 8; $i++) {
                    $cols[] = \Filament\Forms\Components\Select::make('col_'.$i)
                        ->label(fn () => __('Column :n', ['n' => $i + 1]).(isset($pl['cols'][$i]) ? ' – '.$pl['cols'][$i] : ''))
                        ->options($rooms)->placeholder(__('– not used –'))
                        ->visible(function (\Filament\Forms\Get $get) use ($i) {
                            $rows = self::parseList((string) $get('table'), (int) ($get('year') ?: now()->year));

                            return $rows && $i < max(array_map(fn ($r) => count($r[1]), $rows));
                        });
                }
                $fields[] = \Filament\Forms\Components\Grid::make(4)->schema($cols);
                $fields[] = \Filament\Forms\Components\Toggle::make('manual')->label(__('Prices from our price list (Aries only for terms and free places)'))
                    ->helperText(__('On: the nightly sync with the old system does not change these prices any more.'));

                return $fields;
            })
            ->action(function (array $data) {
                $rows = self::parseList((string) $data['table'], (int) $data['year']);
                $map = [];
                for ($i = 0; $i < 8; $i++) {
                    if (! empty($data['col_'.$i])) {
                        $map[$i] = (int) $data['col_'.$i];
                    }
                }
                if (! $rows || ! $map) {
                    Notification::make()->danger()->title(__('Nothing to save: no terms found or no room chosen.'))->send();

                    return;
                }
                /** @var Accommodation $a */
                $a = $this->record;
                $roomIds = $a->rooms()->pluck('id')->all();
                $n = 0;
                $newTerms = 0;
                TermPrice::$quiet = true;
                try {
                    DB::transaction(function () use ($rows, $map, $a, $roomIds, &$n, &$newTerms) {
                        foreach ($rows as [[$from, $to], $prices]) {
                            $term = $a->stayTerms()->whereDate('date_from', $from)->whereDate('date_to', $to)->first();
                            if (! $term) {
                                $term = $a->stayTerms()->create(['date_from' => $from, 'date_to' => $to, 'is_active' => true]);
                                $newTerms++;
                            }
                            foreach ($map as $i => $roomId) {
                                if (! isset($prices[$i]) || ! in_array($roomId, $roomIds, true)) {
                                    continue;
                                }
                                $tp = TermPrice::query()->firstOrNew(['stay_term_id' => $term->id, 'accommodation_room_id' => $roomId]);
                                $tp->price = $prices[$i];
                                $tp->price_manual = true;   // the sync with the old system never changes it
                                $tp->save();
                                $n++;
                            }
                        }
                    });
                } finally {
                    TermPrice::$quiet = false;
                }
                $a->forceFill(['manual_prices' => (bool) ($data['manual'] ?? false), 'book_by_terms' => true])->save();
                $a->touch();
                $this->loadCells();
                Notification::make()->success()->title(__('Saved'))
                    ->body(__(':n prices saved, :t new terms.', ['n' => $n, 't' => $newTerms]))->send();
            });
    }

    protected function getViewData(): array
    {
        return ['terms' => $this->terms(), 'rooms' => $this->record->rooms()->get()];
    }
}

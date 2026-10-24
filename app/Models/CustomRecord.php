<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAgency;
use App\Models\Concerns\HasSlug;
use App\Models\Concerns\Localized;
use App\Models\Concerns\SyncsToWebsite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** A record of a type defined by the super admin (e.g. one transfer, one rent-a-car vehicle) */
class CustomRecord extends Model
{
    use BelongsToAgency, HasSlug, Localized, SyncsToWebsite;

    protected $guarded = ['id'];

    public const TRANSLATABLE = ['title', 'short_description', 'description'];

    public static function websiteType(): string
    {
        return 'custom';
    }

    protected function casts(): array
    {
        return array_fill_keys(self::TRANSLATABLE, 'array') + [
            'gallery' => 'array', 'data' => 'array', 'price_from' => 'decimal:2',
            'booking_enabled' => 'boolean', 'is_active' => 'boolean', 'is_featured' => 'boolean',
        ];
    }

    public function recordType(): BelongsTo
    {
        return $this->belongsTo(RecordType::class);
    }

    public function bookings()
    {
        return $this->morphMany(Booking::class, 'bookable');
    }

    /** How many times the price counts for a booking: persons, days, nights or once */
    public static function units(self $r, Booking $b): int
    {
        $pax = max(1, (int) $b->adults + (int) $b->children);
        $days = $b->date_from && $b->date_to ? max(1, (int) $b->date_from->diffInDays($b->date_to)) : 1;

        return match ($r->recordType?->price_label) {
            'per_person' => $pax,
            'per_day' => $days,
            'per_night' => $days,
            default => max(1, (int) $b->quantity),
        };
    }

    /** Field values ready to show: [key, label {mk,en}, type, value, text {mk,en}] (empty values left out) */
    public function displayFields(bool $websiteOnly = true): array
    {
        $out = [];
        $data = (array) $this->data;
        foreach ($this->recordType?->fieldDefs() ?? [] as $f) {
            if ($websiteOnly && ($f['show_on_website'] ?? true) === false) {
                continue;
            }
            $v = $data[$f['key']] ?? null;
            if ($v === null || $v === '' || $v === []) {
                continue;
            }
            $type = $f['type'] ?? 'text';
            $text = fn ($val) => match ($type) {
                'toggle' => $val ? '✓' : '✗',
                'date' => \Carbon\Carbon::parse($val)->format('d.m.Y'),
                'datetime' => \Carbon\Carbon::parse($val)->format('d.m.Y H:i'),
                'price' => number_format((float) $val, 2, ',', '.').' '.$this->currency,
                'multiselect' => implode(', ', (array) $val),
                'image', 'file' => Storage::disk('public')->url($val),
                'gallery' => array_map(fn ($p) => Storage::disk('public')->url($p), (array) $val),
                'list' => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $val)))),
                default => is_array($val) ? ($val['mk'] ?? implode(', ', $val)) : (string) $val,
            };
            // text fields can be in two languages: {mk, en}
            $valMk = is_array($v) && array_key_exists('mk', $v) ? $v['mk'] : $v;
            $valEn = is_array($v) && array_key_exists('en', $v) ? ($v['en'] ?: $v['mk']) : $v;
            $out[] = [
                'key' => $f['key'], 'type' => $type,
                'label' => ['mk' => $f['label']['mk'] ?? $f['key'], 'en' => ($f['label']['en'] ?? null) ?: ($f['label']['mk'] ?? $f['key'])],
                'suffix' => $f['suffix'] ?? null,
                'value' => ['mk' => $text($valMk), 'en' => $text($valEn)],
                'raw' => in_array($type, ['number', 'price', 'toggle', 'date', 'datetime'], true) ? $valMk : null,
            ];
        }

        return $out;
    }
}

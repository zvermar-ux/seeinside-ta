<?php

namespace App\Services\Bookings;

use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\Charter;
use App\Models\Event;
use App\Models\TermPrice;
use App\Models\Tour;

/**
 * Calculation of a booking as lines (like the old "Пресметка"):
 *   base        – ADL 2 × 399, CHD 1 × 299, Room 7 nights …
 *   supplement  – transport from Skopje, extras …
 *   discount    – early booking, agreed discount %
 */
class BookingPricing
{
    public static function line(string $kind, string $label, float $qty, float $price): array
    {
        return ['kind' => $kind, 'label' => $label, 'qty' => $qty, 'price' => round($price, 2), 'total' => round($qty * $price, 2)];
    }

    public static function suggest(Booking $b): array
    {
        $item = $b->bookable;
        $adults = max(0, (int) $b->adults);
        $kids = max(0, (int) $b->children);
        $pax = max(1, $adults + $kids);
        $lines = [];

        if ($item instanceof Tour && ($dep = $b->departure)) {
            $regularA = (float) ($dep->price_adult ?? $item->price_adult ?? 0);
            $promoA = (float) ($dep->adultPrice() ?? $regularA);
            $child = (float) ($dep->childPrice() ?? $promoA);
            if ($adults) {
                $lines[] = self::line('base', 'ADL', $adults, $regularA ?: $promoA);
            }
            if ($kids) {
                $lines[] = self::line('base', 'CHD', $kids, $child);
            }
            if ($regularA > 0 && $promoA < $regularA && $adults) {
                $label = $dep->is_last_minute && $dep->last_minute_price !== null ? 'Last minute' : ($item->first_minute_label ?: ($item->isLastMinuteActive() ? 'Last minute' : 'Early Booking'));
                $lines[] = self::line('discount', $label, $adults, -($regularA - $promoA));
            }
            if ($b->departure_place_id && ($link = $item->departurePlaceLinks()->where('departure_place_id', $b->departure_place_id)->first()) && (float) $link->surcharge) {
                $lines[] = self::line('supplement', __('Transport surcharge').' '.($b->departurePlace?->tr('name') ?? ''), $pax, (float) $link->surcharge);
            }
        } elseif ($item instanceof Accommodation) {
            $qty = max(1, (int) $b->quantity);
            $room = $b->room;
            $nights = $b->nights();
            $tp = $b->stay_term_id && $b->accommodation_room_id
                ? TermPrice::query()->where('stay_term_id', $b->stay_term_id)->where('accommodation_room_id', $b->accommodation_room_id)->first() : null;
            if ($tp && $tp->price !== null) {
                $perPerson = $item->term_price_type === 'per_person';
                $lines[] = self::line('base', trim(($room?->tr('name') ?? '').' · '.$b->stayTerm?->label().' ('.$nights.' '.__('nights').')'), $perPerson ? $pax : $qty, (float) $tp->price);
            } elseif ($room && $b->date_from && $b->date_to) {
                // the booking itself occupies the room – price only, no availability check
                $offer = collect($item->quote($b->date_from->toDateString(), $b->date_to->toDateString(), max(1, $adults), $kids, false))->firstWhere('room_id', $room->id);
                if ($offer) {
                    $lines[] = self::line('base', trim($room->tr('name').' · '.$nights.' '.__('nights')), $qty, (float) $offer['total']);
                }
            }
        } elseif ($item instanceof Event && ($t = $b->ticket)) {
            $lines[] = self::line('base', $t->tr('name'), max(1, (int) $b->quantity), (float) $t->price);
        } elseif ($item instanceof Charter) {
            $lines[] = self::line('base', $item->region.' '.$item->departure_date?->format('d.m.Y'), $pax, (float) $item->price);
        }

        foreach ((array) $b->extras as $e) {
            if (! is_array($e) || ! isset($e['price'])) {
                continue;
            }
            $lines[] = self::line((float) $e['price'] < 0 ? 'discount' : 'supplement', (string) ($e['name'] ?? __('Extra')), ($e['price_type'] ?? '') === 'per_person' ? $pax : 1, (float) $e['price']);
        }

        if ((float) $b->discount_percent > 0) {
            $sub = array_sum(array_column($lines, 'total'));
            $lines[] = self::line('discount', __('Discount').' '.rtrim(rtrim(number_format((float) $b->discount_percent, 2, ',', ''), '0'), ',').'%', 1, -round($sub * (float) $b->discount_percent / 100, 2));
        }

        // agreed total differs from the calculation → show the difference, so the document adds up
        $sum = round(array_sum(array_column($lines, 'total')), 2);
        if ($b->total !== null && $lines && abs($sum - (float) $b->total) > 0.009) {
            $diff = round((float) $b->total - $sum, 2);
            $lines[] = self::line($diff < 0 ? 'discount' : 'supplement', $diff < 0 ? __('Discount') : __('Price correction'), 1, $diff);
        }
        if (! $lines && $b->total !== null) {
            $lines[] = self::line('base', $b->itemTitle(), 1, (float) $b->total);
        }

        return $lines;
    }

    public static function sum(array $lines): float
    {
        return round(array_sum(array_map(fn ($l) => (float) ($l['total'] ?? ((float) ($l['qty'] ?? 1) * (float) ($l['price'] ?? 0))), $lines)), 2);
    }
}

<?php

namespace App\Services\Hotels;

use App\Models\SupplierConnection;

/**
 * A booking system that sells hotels (Hotelbeds now; others plug in the same way).
 *
 * search() returns normalized hotels:
 *   [['code' => '123', 'name' => …, 'stars' => 4, 'currency' => 'EUR', 'rooms' => [[
 *       'code' => 'DBL.ST', 'name' => …, 'rates' => [[
 *           'key' => rate key, 'net' => 512.40, 'board_code' => 'HB', 'board' => 'Half board', 'recheck' => bool,
 *           'refundable' => bool, 'cancellation' => [['amount' => 100.0, 'from' => ISO date]], 'allotment' => 3,
 *           'rooms' => 1, 'adults' => 2, 'children' => 1, 'pay_at_hotel' => bool, 'comments' => '',
 *       ]]]]]]
 */
interface HotelSupplier
{
    /** Quick check that the keys work: ['ok' => bool, 'message' => string] */
    public function test(SupplierConnection $c): array;

    /** Destinations of the given countries: [['code', 'name', 'country_code', 'country_name', 'zones' => [...]]] */
    public function destinations(SupplierConnection $c, array $countryCodes): array;

    /** One page of hotel descriptions of a destination: ['hotels' => [normalized content], 'total' => int] */
    public function hotels(SupplierConnection $c, string $destinationCode, int $from, int $to): array;

    /** Live prices: $q = ['from' => Y-m-d, 'to' => Y-m-d, 'rooms' => [['adults' => 2, 'children_ages' => [5]]], 'destination' => code | 'hotels' => [codes], 'min_stars' => int] */
    public function search(SupplierConnection $c, array $q): array;

    /** Confirms the price of a rate before booking: normalized hotel with that one rate (comments, final cancellation) */
    public function checkRate(SupplierConnection $c, string $rateKey): array;

    /** Books for real: ['reference' => supplier ref, 'status' => 'CONFIRMED', 'net' => float, 'raw' => array] */
    public function book(SupplierConnection $c, array $data): array;

    /** Cancels: ['status' => 'CANCELLED', 'fee' => float|null] */
    public function cancel(SupplierConnection $c, string $reference, bool $simulate = false): array;
}

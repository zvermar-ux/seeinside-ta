<?php

namespace App\Services\Hotels;

use RuntimeException;

/** The supplier's price went up between the search and the booking: the visitor confirms the new price */
class PriceChanged extends RuntimeException
{
    public function __construct(public float $price, public string $currency)
    {
        parent::__construct(__('The hotel changed the price to :price :cur. Confirm to continue.', ['price' => $price, 'cur' => $currency]));
    }
}

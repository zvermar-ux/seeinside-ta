<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentStatus: string implements HasLabel, HasColor
{
    case Unpaid = 'unpaid';
    case Deposit = 'deposit';
    case Paid = 'paid';
    case Refunded = 'refunded';

    public function getLabel(): string
    {
        return __(match ($this) {
            self::Unpaid => 'Unpaid',
            self::Deposit => 'Deposit paid',
            self::Paid => 'Paid',
            self::Refunded => 'Refunded',
        });
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Unpaid => 'danger',
            self::Deposit => 'warning',
            self::Paid => 'success',
            self::Refunded => 'gray',
        };
    }

    /** Labels in both languages for the API */
    public function labels(): array
    {
        $mk = match ($this) {
            self::Unpaid => 'Неплатено',
            self::Deposit => 'Платен аванс',
            self::Paid => 'Платено',
            self::Refunded => 'Вратено',
        };
        $en = match ($this) {
            self::Unpaid => 'Unpaid',
            self::Deposit => 'Deposit paid',
            self::Paid => 'Paid',
            self::Refunded => 'Refunded',
        };

        return ['mk' => $mk, 'en' => $en];
    }
}

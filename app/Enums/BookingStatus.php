<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BookingStatus: string implements HasLabel, HasColor
{
    case Inquiry = 'inquiry';
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    public function getLabel(): string
    {
        return __(match ($this) {
            self::Inquiry => 'Inquiry',
            self::Pending => 'Pending',
            self::Confirmed => 'Confirmed',
            self::Cancelled => 'Cancelled',
            self::Completed => 'Completed',
        });
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Inquiry => 'info',
            self::Pending => 'warning',
            self::Confirmed => 'success',
            self::Cancelled => 'danger',
            self::Completed => 'gray',
        };
    }

    /** Labels in both languages for the API */
    public function labels(): array
    {
        $mk = match ($this) {
            self::Inquiry => 'Барање',
            self::Pending => 'Во обработка',
            self::Confirmed => 'Потврдена',
            self::Cancelled => 'Откажана',
            self::Completed => 'Реализирана',
        };
        $en = match ($this) {
            self::Inquiry => 'Inquiry',
            self::Pending => 'Pending',
            self::Confirmed => 'Confirmed',
            self::Cancelled => 'Cancelled',
            self::Completed => 'Completed',
        };

        return ['mk' => $mk, 'en' => $en];
    }
}

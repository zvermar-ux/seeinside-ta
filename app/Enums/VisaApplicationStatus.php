<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum VisaApplicationStatus: string implements HasLabel, HasColor
{
    case New = 'new';
    case InReview = 'in_review';
    case DocumentsRequested = 'documents_requested';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return __(match ($this) {
            self::New => 'New',
            self::InReview => 'In review',
            self::DocumentsRequested => 'Documents requested',
            self::Submitted => 'Submitted to embassy',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
        });
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'info',
            self::InReview => 'warning',
            self::DocumentsRequested => 'warning',
            self::Submitted => 'primary',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Cancelled => 'gray',
        };
    }

    /** Labels in both languages for the API */
    public function labels(): array
    {
        $mk = match ($this) {
            self::New => 'Ново',
            self::InReview => 'Во обработка',
            self::DocumentsRequested => 'Побарани документи',
            self::Submitted => 'Предадено во амбасада',
            self::Approved => 'Одобрена',
            self::Rejected => 'Одбиена',
            self::Cancelled => 'Откажано',
        };
        $en = match ($this) {
            self::New => 'New',
            self::InReview => 'In review',
            self::DocumentsRequested => 'Documents requested',
            self::Submitted => 'Submitted to embassy',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
        };

        return ['mk' => $mk, 'en' => $en];
    }
}

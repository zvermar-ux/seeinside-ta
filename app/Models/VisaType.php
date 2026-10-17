<?php

namespace App\Models;

use App\Enums\VisaCategory;
use App\Models\Concerns\BelongsToAgency;
use App\Models\Concerns\HasMedia;
use App\Models\Concerns\HasPromotions;
use App\Models\Concerns\HasSlug;
use App\Models\Concerns\Localized;
use App\Models\Concerns\SyncsToWebsite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VisaType extends Model
{
    use BelongsToAgency, HasMedia, HasPromotions, HasSlug, Localized, SyncsToWebsite;

    protected $guarded = ['id'];

    public const TRANSLATABLE = ['name', 'short_description', 'description', 'requirements', 'processing_time', 'validity', 'seo_title', 'seo_description'];

    public static function websiteType(): string
    {
        return 'visa';
    }

    protected function casts(): array
    {
        return array_fill_keys(self::TRANSLATABLE, 'array') + [
            'category' => VisaCategory::class,
            'questionnaire' => 'array',
            'required_documents' => 'array',
            'online_payment' => 'boolean',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'service_fee' => 'decimal:2',
            'consular_fee' => 'decimal:2',
            'express_fee' => 'decimal:2',
        ];
    }

    protected function slugSource(): string
    {
        return trim($this->country.' '.($this->name['mk'] ?? $this->name['en'] ?? ''));
    }

    public function totalPrice(bool $express = false): float
    {
        return (float) $this->promoPrice((float) $this->service_fee + (float) $this->consular_fee) + ($express ? (float) $this->express_fee : 0);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(VisaApplication::class);
    }
}

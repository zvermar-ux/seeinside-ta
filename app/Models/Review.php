<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAgency;
use App\Services\Website\WebsiteNotifier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Customer review: arrives from the website as "pending", shown on the website after approval */
class Review extends Model
{
    use BelongsToAgency;

    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';

    /** website type => model */
    public const TYPES = [
        'tour' => Tour::class,
        'event' => Event::class,
        'accommodation' => Accommodation::class,
        'visa' => VisaType::class,
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['rating' => 'integer', 'is_featured' => 'boolean', 'approved_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        // the website keeps one list of approved reviews: refresh it when anything visible changes
        static::saved(function (Review $r) {
            if ($r->status === self::APPROVED || $r->wasChanged('status') || $r->getOriginal('status') === self::APPROVED) {
                WebsiteNotifier::listChanged($r->agency_id, 'reviews');
            }
        });
        static::deleted(fn (Review $r) => $r->status === self::APPROVED ? WebsiteNotifier::listChanged($r->agency_id, 'reviews') : null);
    }

    public function reviewable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeApproved(Builder $q): Builder
    {
        return $q->where('status', self::APPROVED);
    }

    public static function statuses(): array
    {
        return [self::PENDING => __('Waiting for approval'), self::APPROVED => __('Approved'), self::REJECTED => __('Rejected')];
    }

    public function websiteItemType(): ?string
    {
        if (! $this->reviewable_type) {
            return null;
        }
        $class = \Illuminate\Database\Eloquent\Relations\Relation::getMorphedModel($this->reviewable_type) ?? $this->reviewable_type;

        return array_search($class, self::TYPES, true) ?: null;
    }

    public function itemTitle(): ?string
    {
        $m = $this->reviewable;
        if (! $m) {
            return null;
        }
        $v = $m->title ?? $m->name ?? null;

        return is_array($v) ? ($v[app()->getLocale()] ?? $v['mk'] ?? reset($v)) : $v;
    }
}

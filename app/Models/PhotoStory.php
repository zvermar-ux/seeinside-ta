<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAgency;
use App\Services\Website\WebsiteNotifier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** Travel photos of a client: from the website as "pending", on the website after approval (like reviews) */
class PhotoStory extends Model
{
    use BelongsToAgency;

    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['photos' => 'array', 'is_featured' => 'boolean', 'approved_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saved(function (PhotoStory $s) {
            if ($s->status === self::APPROVED || $s->wasChanged('status') || $s->getOriginal('status') === self::APPROVED) {
                WebsiteNotifier::listChanged($s->agency_id, 'photo_stories');
            }
        });
        static::deleted(function (PhotoStory $s) {
            foreach ((array) $s->photos as $p) {
                Storage::disk('public')->delete(array_filter([$p['path'] ?? null, $p['thumb'] ?? null]));
            }
            if ($s->status === self::APPROVED) {
                WebsiteNotifier::listChanged($s->agency_id, 'photo_stories');
            }
        });
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class)->withoutGlobalScopes();
    }

    public function scopeApproved(Builder $q): Builder
    {
        return $q->where('status', self::APPROVED);
    }

    public static function statuses(): array
    {
        return [self::PENDING => __('Waiting for approval'), self::APPROVED => __('Approved'), self::REJECTED => __('Rejected')];
    }

    /** Paths of the photos (for the admin form) */
    public function photoPaths(): array
    {
        return array_values(array_filter(array_map(fn ($p) => is_array($p) ? ($p['path'] ?? null) : $p, (array) $this->photos)));
    }
}

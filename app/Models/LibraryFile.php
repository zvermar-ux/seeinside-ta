<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/** One image in the media library (the file itself stays on the "public" disk) */
class LibraryFile extends Model
{
    protected $guarded = ['id'];

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    /** What the current user may see: own agency + shared files; super admin everything */
    public function scopeVisible(Builder $q, ?int $agencyId = null): Builder
    {
        $u = Auth::user();
        if ($u?->isSuperAdmin()) {
            return $agencyId ? $q->where(fn ($w) => $w->where('agency_id', $agencyId)->orWhereNull('agency_id')) : $q;
        }

        return $q->where(fn ($w) => $w->where('agency_id', $u?->agency_id ?? -1)->orWhereNull('agency_id'));
    }

    public function sizeLabel(): string
    {
        $s = (int) $this->size;

        return $s >= 1048576 ? number_format($s / 1048576, 1).' MB' : max(1, round($s / 1024)).' KB';
    }
}

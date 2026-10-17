<?php

namespace App\Models\Concerns;

use App\Models\Agency;
use App\Models\Scopes\AgencyScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait BelongsToAgency
{
    public static function bootBelongsToAgency(): void
    {
        static::addGlobalScope(new AgencyScope());

        static::creating(function ($model) {
            if (empty($model->agency_id) && Auth::hasUser() && Auth::user()->agency_id) {
                $model->agency_id = Auth::user()->agency_id;
            }
        });
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }
}

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

        // "saving" runs before "creating", so the agency is known for slug checks and other hooks
        static::saving(function ($model) {
            if (! $model->exists && empty($model->agency_id) && Auth::hasUser() && Auth::user()->agency_id) {
                $model->agency_id = Auth::user()->agency_id;
            }
        });
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }
}

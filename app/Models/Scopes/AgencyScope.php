<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Limits every query to the agency of the logged-in user.
 * Super admins (and console commands without a user) see all agencies.
 */
class AgencyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! Auth::hasUser()) {
            return;
        }

        $user = Auth::user();

        if ($user->isSuperAdmin()) {
            return;
        }

        $builder->where($model->qualifyColumn('agency_id'), $user->agency_id ?? 0);
    }
}

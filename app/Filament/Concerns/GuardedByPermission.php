<?php

namespace App\Filament\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Maps Filament resource abilities to "<module>.view" / "<module>.manage" permissions.
 * Resources using this trait must implement permissionModule().
 */
trait GuardedByPermission
{
    abstract public static function permissionModule(): string;

    public static function can(string $action, ?Model $record = null): bool
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        // Never allow touching records that belong to another agency
        if ($record && $record->getAttribute('agency_id') !== null && (int) $record->getAttribute('agency_id') !== (int) $user->agency_id) {
            return false;
        }

        $module = static::permissionModule();
        $permission = in_array($action, ['viewAny', 'view'], true) ? "{$module}.view" : "{$module}.manage";

        return $user->can($permission);
    }

    public static function isSuperAdmin(): bool
    {
        return (bool) Auth::user()?->isSuperAdmin();
    }
}

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
        // the super admin turned this part off for the agency
        if (($agencyModule = \App\Filament\Concerns\GuardedByPermission::agencyModuleFor($module)) && ! \App\Models\Agency::moduleOn($agencyModule)) {
            return false;
        }
        $permission = in_array($action, ['viewAny', 'view'], true) ? "{$module}.view" : "{$module}.manage";

        return $user->can($permission);
    }

    /** permission module → module the super admin switches per agency */
    public static function agencyModuleFor(string $module): ?string
    {
        return [
            'tours' => 'tours', 'events' => 'events', 'accommodations' => 'accommodations', 'visas' => 'visas', 'visa_applications' => 'visas',
            'charters' => 'charters', 'finance' => 'finance', 'reports' => 'reports', 'reviews' => 'reviews', 'live_chat' => 'live_chat',
            'ai' => 'ai', 'objects' => 'imports', 'imports' => 'imports',
        ][$module] ?? null;
    }

    public static function isSuperAdmin(): bool
    {
        return (bool) Auth::user()?->isSuperAdmin();
    }
}

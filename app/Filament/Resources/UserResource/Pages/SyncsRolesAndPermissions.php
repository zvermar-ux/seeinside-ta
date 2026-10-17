<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

trait SyncsRolesAndPermissions
{
    protected ?string $pendingRole = null;

    protected array $pendingPermissions = [];

    protected function extractRoleData(array $data): array
    {
        $role = $data['role'] ?? Permissions::OPERATOR;
        if (! array_key_exists($role, UserResource::assignableRoles())) {
            $role = Permissions::OPERATOR;
        }

        $this->pendingRole = $role;
        $this->pendingPermissions = array_values(array_intersect(
            (array) ($data['permission_names'] ?? []),
            UserResource::assignablePermissions(),
        ));

        // Agency users always stay in the creator's agency
        if (! Auth::user()->isSuperAdmin()) {
            $data['agency_id'] = Auth::user()->agency_id;
        } elseif ($role === Permissions::SUPER_ADMIN) {
            $data['agency_id'] = null;
        }

        return Arr::except($data, ['role', 'permission_names']);
    }

    protected function applyRoleData(User $user): void
    {
        $user->syncRoles([$this->pendingRole]);
        $user->syncPermissions($this->pendingRole === Permissions::SUPER_ADMIN ? [] : $this->pendingPermissions);
    }
}

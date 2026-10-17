<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $created = [];
        foreach (Permissions::all() as $name) {
            $p = Permission::findOrCreate($name, 'web');
            if ($p->wasRecentlyCreated) {
                $created[] = $name;
            }
        }

        foreach ([Permissions::SUPER_ADMIN, Permissions::AGENCY_ADMIN, Permissions::OPERATOR] as $role) {
            Role::findOrCreate($role, 'web');
        }

        // New modules: give them to existing users according to their role defaults
        if ($created) {
            foreach (User::role([Permissions::AGENCY_ADMIN, Permissions::OPERATOR])->get() as $user) {
                $grant = array_values(array_intersect($created, Permissions::roleDefaults((string) $user->primaryRole())));
                if ($grant) {
                    $user->givePermissionTo($grant);
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

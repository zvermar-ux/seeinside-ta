<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateSuperAdmin extends Command
{
    protected $signature = 'ta:superadmin {--name=} {--email=}';

    protected $description = 'Create (or promote) a super admin user';

    public function handle(): int
    {
        $this->call('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);

        $name = $this->option('name') ?: $this->ask('Name / Име');
        $email = $this->option('email') ?: $this->ask('E-mail');

        $user = User::where('email', $email)->first();
        $password = null;

        if (! $user || $this->confirm('User exists. Set a new password?', false)) {
            $password = $this->secret('Password / Лозинка (min 8)');
            $v = Validator::make(['email' => $email, 'password' => $password], [
                'email' => 'required|email', 'password' => 'required|min:8',
            ]);
            if ($v->fails()) {
                $this->error(implode("\n", $v->errors()->all()));

                return self::FAILURE;
            }
        }

        $user ??= new User();
        $user->fill(['name' => $name ?: $user->name, 'email' => $email, 'is_active' => true, 'agency_id' => null]);
        if ($password) {
            $user->password = $password;
        }
        $user->email_verified_at ??= now();
        $user->save();
        $user->syncRoles([Permissions::SUPER_ADMIN]);

        $this->info("Super admin ready: {$user->email}");

        return self::SUCCESS;
    }
}

<?php

namespace App\Models;

use App\Support\Permissions;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'agency_id', 'name', 'email', 'phone', 'password', 'locale', 'is_active', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(Permissions::SUPER_ADMIN);
    }

    public function isAgencyAdmin(): bool
    {
        return $this->hasRole(Permissions::AGENCY_ADMIN);
    }

    public function primaryRole(): ?string
    {
        foreach ([Permissions::SUPER_ADMIN, Permissions::AGENCY_ADMIN, Permissions::OPERATOR] as $role) {
            if ($this->hasRole($role)) {
                return $role;
            }
        }

        return null;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->agency_id !== null && (bool) $this->agency?->is_active;
    }
}

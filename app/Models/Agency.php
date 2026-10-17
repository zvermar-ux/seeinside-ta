<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Agency extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['mail_password', 'payment_settings'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'mail_password' => 'encrypted',
            'mail_port' => 'integer',
            'payment_settings' => 'encrypted:array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Agency $agency) {
            if (blank($agency->slug)) {
                $base = Str::slug($agency->name) ?: 'agency';
                $slug = $base;
                $i = 2;
                while (static::where('slug', $slug)->where('id', '!=', $agency->id ?? 0)->exists()) {
                    $slug = $base.'-'.$i++;
                }
                $agency->slug = $slug;
            }
        });
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function tourOperators(): HasMany
    {
        return $this->hasMany(TourOperator::class);
    }

    public function travelObjects(): HasMany
    {
        return $this->hasMany(TravelObject::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(Group::class);
    }

    public function subagents(): HasMany
    {
        return $this->hasMany(Subagent::class);
    }
}

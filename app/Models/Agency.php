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
            'documents' => 'array',
        ];
    }

    /** Document & finance settings with defaults (contract, invoices, cash register, exchange rate) */
    public function doc(string $key, mixed $default = null): mixed
    {
        $defaults = [
            'base_currency' => 'MKD',
            'eur_rate' => 61.5,
            'vat_rate' => 0,
            'invoice_days' => 8,
            'registers' => ['main' => 'Главна благајна'],
            'license' => 'Лиценца А',
            'payment_clause' => 'Плаќањето е исклучиво во денарска противвредност по куповниот курс на деловна банка за откуп на девизи од девизен пазар (т.е. продажен курс према организаторот).',
            'terms_clause' => 'Општите услови на патување се составен дел на договорот.',
            'declaration' => 'Изјавувам дека сум запознат со програмата и со општите услови на патување и дека во целост ги прифаќам.',
            'invoice_text' => 'Ве задолжуваме за',
        ];

        return data_get($this->documents ?? [], $key) ?? $default ?? ($defaults[$key] ?? null);
    }

    public function eurRate(): float
    {
        return (float) ($this->doc('eur_rate') ?: 61.5);
    }

    /** Amount in the agency's base currency (MKD) */
    public function toBase(float $amount, ?string $currency, ?float $rate = null): float
    {
        $base = $this->doc('base_currency');
        if (! $currency || $currency === $base) {
            return round($amount, 2);
        }
        $rate ??= $currency === 'EUR' ? $this->eurRate() : (float) ($this->doc('rates.'.$currency) ?: 1);

        return round($amount * $rate, 2);
    }

    protected static function booted(): void
    {
        static::saved(fn ($m) => \App\Services\Website\WebsiteNotifier::metaChanged($m->id));
        static::deleted(fn ($m) => \App\Services\Website\WebsiteNotifier::metaChanged($m->id));
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

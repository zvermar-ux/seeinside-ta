<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Agency extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['mail_password', 'payment_settings', 'messaging'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'mail_password' => 'encrypted',
            'mail_port' => 'integer',
            'payment_settings' => 'encrypted:array',
            'documents' => 'array',
            'messaging' => 'encrypted:array',
            'modules' => 'array',
        ];
    }

    /** Modules the super admin can turn on / off per agency (null = all on) */
    public static function moduleOptions(): array
    {
        $out = [
            'tours' => __('Organized tours'),
            'events' => __('Events'),
            'accommodations' => __('Hotels & apartments'),
            'visas' => __('Visa services'),
            'charters' => __('Charters'),
            'finance' => __('Invoices, cash register & supplier invoices'),
            'reports' => __('Reports, costs & profit'),
            'reviews' => __('Reviews'),
            'live_chat' => __('Live chat & chatbot'),
            'ai' => __('AI assistant (texts and images)'),
            'imports' => __('Imported offer (Aries)'),
            'messaging' => __('Viber & WhatsApp messages'),
        ];
        try {
            foreach (RecordType::query()->orderBy('sort_order')->get() as $t) {
                $out['custom:'.$t->slug] = $t->label().' ('.__('custom type').')';
            }
        } catch (\Throwable) {
        }

        return $out;
    }

    public function hasModule(string $module): bool
    {
        $mods = $this->modules;
        if ($mods === null) {
            return true;    // nothing chosen yet → everything on
        }

        return in_array($module, (array) $mods, true);
    }

    /** Is the module on for the agency of the logged-in user? (super admin: always) */
    public static function moduleOn(string $module): bool
    {
        $u = auth()->user();
        if (! $u || $u->isSuperAdmin() || ! $u->agency_id) {
            return true;
        }
        static $cache = [];

        return $cache[$u->agency_id.'|'.$module] ??= (bool) self::find($u->agency_id)?->hasModule($module);
    }

    /** Messaging settings (channels, tokens, templates) */
    public function msg(string $key, mixed $default = null): mixed
    {
        return data_get((array) $this->messaging, $key, $default);
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

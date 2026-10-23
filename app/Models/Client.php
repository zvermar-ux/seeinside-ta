<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Person (passenger / customer) or company in the agency's client list */
class Client extends Model
{
    use BelongsToAgency;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['birth_date' => 'date', 'passport_expiry' => 'date', 'is_problem' => 'boolean'];
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->type === 'company'
            ? (string) $this->company_name
            : trim(mb_strtoupper((string) $this->last_name).' '.mb_strtoupper((string) $this->first_name));
    }

    public function label(): string
    {
        $bits = array_filter([
            $this->birth_date?->format('d.m.Y'),
            $this->passport_no && $this->passport_no !== '/' ? $this->passport_no : null,
            $this->city,
            $this->type === 'company' ? $this->tax_number : null,
        ]);

        return $this->display_name.($bits ? ' · '.implode(' · ', $bits) : '').($this->is_problem ? ' ⚠' : '');
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $q;
        }
        foreach (preg_split('/\s+/u', $term) as $w) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $w).'%';
            $q->where(fn ($x) => $x->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)->orWhere('company_name', 'like', $like)
                ->orWhere('passport_no', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('email', 'like', $like)->orWhere('tax_number', 'like', $like));
        }

        return $q;
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** Passenger row (as stored on a booking) from this person */
    public function toPassenger(): array
    {
        return [
            'client_id' => $this->id,
            'title' => $this->title,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'gender' => $this->gender,
            'birth_date' => $this->birth_date?->toDateString(),
            'passport' => $this->passport_no,
            'phone' => $this->phone,
            'address' => $this->address,
            'city' => $this->city,
        ];
    }

    /**
     * Find the person in the client list (same name + date of birth, or same passport) or add them.
     * Used when passengers are entered on a booking, so the list fills itself.
     */
    public static function fromPassenger(array $p, int $agencyId): ?self
    {
        $first = trim((string) ($p['first_name'] ?? ''));
        $last = trim((string) ($p['last_name'] ?? ''));
        if ($first === '' && $last === '') {
            return null;
        }
        $q = static::withoutGlobalScopes()->where('agency_id', $agencyId)->where('type', 'person');
        $found = null;
        if (! empty($p['client_id'])) {
            $found = (clone $q)->whereKey($p['client_id'])->first();
        }
        if (! $found && ! empty($p['passport']) && strlen(trim($p['passport'])) > 3) {
            $found = (clone $q)->where('passport_no', trim($p['passport']))->first();
        }
        if (! $found) {
            $found = (clone $q)->whereRaw('lower(first_name) = ?', [mb_strtolower($first)])->whereRaw('lower(last_name) = ?', [mb_strtolower($last)])
                ->when(! empty($p['birth_date']), fn ($x) => $x->whereDate('birth_date', $p['birth_date']), fn ($x) => $x->whereNull('birth_date'))
                ->first();
        }
        $data = array_filter([
            'title' => $p['title'] ?? null, 'gender' => $p['gender'] ?? null, 'birth_date' => $p['birth_date'] ?? null,
            'passport_no' => $p['passport'] ?? null, 'phone' => $p['phone'] ?? null, 'address' => $p['address'] ?? null, 'city' => $p['city'] ?? null, 'email' => $p['email'] ?? null,
        ], fn ($v) => filled($v));
        if ($found) {
            // fill in what was missing, never overwrite with empty values
            $found->fill(array_filter($data, fn ($v, $k) => blank($found->getAttribute($k)), ARRAY_FILTER_USE_BOTH))->save();

            return $found;
        }

        return static::create(['agency_id' => $agencyId, 'type' => 'person', 'first_name' => $first, 'last_name' => $last] + $data);
    }
}

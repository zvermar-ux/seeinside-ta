<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A hotel/apartment or package of another booking system (the old Aries system), for linking our offers */
class SupplierProduct extends Model
{
    use BelongsToAgency;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['rooms' => 'array', 'is_active' => 'boolean'];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(SupplierConnection::class, 'supplier_connection_id');
    }

    public function label(): string
    {
        return $this->name.' · '.trim(implode(', ', array_filter([$this->city, $this->country])), ', ').' (#'.$this->code.')';
    }

    /** room code => "Room · service" */
    public function roomOptions(): array
    {
        $out = [];
        foreach ((array) $this->rooms as $r) {
            $out[(string) $r['code']] = trim(implode(' · ', array_filter([$r['room'] ?? null, $r['content'] ?? null, $r['service'] ?? null])), ' ·').' (Room-Id '.$r['code'].')';
        }

        return $out;
    }
}

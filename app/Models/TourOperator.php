<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TourOperator extends Model
{
    use BelongsToAgency;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_own' => 'boolean',
            'is_active' => 'boolean',
            'default_markup' => 'decimal:2',
        ];
    }

    public function travelObjects(): HasMany
    {
        return $this->hasMany(TravelObject::class);
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAgency;
use App\Models\Concerns\Localized;
use Illuminate\Database\Eloquent\Model;

class DeparturePlace extends Model
{
    use BelongsToAgency, Localized;

    protected $guarded = ['id'];

    public const DEFAULTS = [
        ['mk' => 'Скопје', 'en' => 'Skopje'], ['mk' => 'Битола', 'en' => 'Bitola'], ['mk' => 'Прилеп', 'en' => 'Prilep'],
        ['mk' => 'Охрид', 'en' => 'Ohrid'], ['mk' => 'Струга', 'en' => 'Struga'], ['mk' => 'Куманово', 'en' => 'Kumanovo'],
        ['mk' => 'Тетово', 'en' => 'Tetovo'], ['mk' => 'Гостивар', 'en' => 'Gostivar'], ['mk' => 'Велес', 'en' => 'Veles'],
        ['mk' => 'Штип', 'en' => 'Shtip'], ['mk' => 'Струмица', 'en' => 'Strumica'], ['mk' => 'Кавадарци', 'en' => 'Kavadarci'],
        ['mk' => 'Неготино', 'en' => 'Negotino'], ['mk' => 'Гевгелија', 'en' => 'Gevgelija'], ['mk' => 'Кочани', 'en' => 'Kochani'],
        ['mk' => 'Кичево', 'en' => 'Kichevo'], ['mk' => 'Ресен', 'en' => 'Resen'],
    ];

    protected function casts(): array
    {
        return ['name' => 'array', 'is_active' => 'boolean'];
    }

    /** Create the default list of Macedonian cities for an agency (once) */
    public static function seedDefaults(int $agencyId): void
    {
        if (static::withoutGlobalScopes()->where('agency_id', $agencyId)->exists()) {
            return;
        }
        foreach (self::DEFAULTS as $i => $name) {
            static::withoutGlobalScopes()->create(['agency_id' => $agencyId, 'name' => $name, 'sort_order' => $i + 1]);
        }
    }
}

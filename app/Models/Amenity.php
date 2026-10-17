<?php

namespace App\Models;

use App\Models\Concerns\Localized;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Amenity extends Model
{
    use Localized;

    protected $guarded = ['id'];

    /** key => [mk, en, icon, category] */
    public const DEFAULTS = [
        'wifi' => ['Брз WiFi', 'Fast WiFi', 'heroicon-o-wifi', 'general'],
        'parking' => ['Паркинг', 'Parking', 'heroicon-o-truck', 'general'],
        'kitchen' => ['Целосна кујна', 'Full kitchen', 'heroicon-o-fire', 'room'],
        'air_conditioning' => ['Клима уред', 'Air conditioning', 'heroicon-o-sun', 'room'],
        'fitness' => ['Фитнес центар', 'Fitness center', 'heroicon-o-bolt', 'sport'],
        'jacuzzi' => ['Џакузи', 'Jacuzzi', 'heroicon-o-sparkles', 'wellness'],
        'kids_club' => ['Детски клуб', 'Kids club', 'heroicon-o-face-smile', 'family'],
        'pool' => ['Базен', 'Swimming pool', 'heroicon-o-lifebuoy', 'wellness'],
        'indoor_pool' => ['Затворен базен', 'Indoor pool', 'heroicon-o-lifebuoy', 'wellness'],
        'sauna' => ['Сауна', 'Sauna', 'heroicon-o-fire', 'wellness'],
        'spa' => ['Спа центар', 'Spa center', 'heroicon-o-sparkles', 'wellness'],
        'ski_shuttle' => ['Превоз до ски-лифт', 'Ski lift shuttle', 'heroicon-o-truck', 'transport'],
        'ski_storage' => ['Ски гардероба', 'Ski storage', 'heroicon-o-archive-box', 'sport'],
        'airport_transfer' => ['Трансфер од аеродром', 'Airport transfer', 'heroicon-o-paper-airplane', 'transport'],
        'restaurant' => ['Ресторан', 'Restaurant', 'heroicon-o-cake', 'food'],
        'bar' => ['Бар', 'Bar', 'heroicon-o-beaker', 'food'],
        'beach' => ['Сопствена плажа', 'Private beach', 'heroicon-o-sun', 'general'],
        'sunbeds' => ['Лежалки и чадори', 'Sunbeds & umbrellas', 'heroicon-o-sun', 'general'],
        'balcony' => ['Балкон', 'Balcony', 'heroicon-o-home', 'room'],
        'sea_view' => ['Поглед на море', 'Sea view', 'heroicon-o-eye', 'room'],
        'tv' => ['ТВ', 'TV', 'heroicon-o-tv', 'room'],
        'fridge' => ['Фрижидер', 'Fridge', 'heroicon-o-cube', 'room'],
        'elevator' => ['Лифт', 'Elevator', 'heroicon-o-arrows-up-down', 'general'],
        'reception_24h' => ['Рецепција 24/7', '24h reception', 'heroicon-o-clock', 'general'],
        'pets' => ['Дозволени миленици', 'Pets allowed', 'heroicon-o-heart', 'general'],
        'accessible' => ['Пристап за лица со попреченост', 'Wheelchair accessible', 'heroicon-o-user', 'general'],
        'playground' => ['Детско игралиште', 'Playground', 'heroicon-o-face-smile', 'family'],
        'laundry' => ['Перење алишта', 'Laundry', 'heroicon-o-sparkles', 'general'],
    ];

    protected function casts(): array
    {
        return ['name' => 'array', 'is_active' => 'boolean'];
    }

    public static function seedDefaults(): void
    {
        $i = 0;
        foreach (self::DEFAULTS as $key => [$mk, $en, $icon, $cat]) {
            static::firstOrCreate(['key' => $key], ['name' => ['mk' => $mk, 'en' => $en], 'icon' => $icon, 'category' => $cat, 'sort_order' => ++$i]);
        }
    }

    public function accommodations(): BelongsToMany
    {
        return $this->belongsToMany(Accommodation::class);
    }
}

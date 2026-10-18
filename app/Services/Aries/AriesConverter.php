<?php

namespace App\Services\Aries;

use App\Enums\AccommodationType;
use App\Enums\ObjectType;
use App\Enums\TourType;
use App\Filament\Resources\AccommodationResource;
use App\Filament\Resources\TourResource;
use App\Models\Accommodation;
use App\Models\Tour;
use App\Models\TravelObject;

/** Turns an imported Aries record into a website offer (tour or accommodation) */
class AriesConverter
{
    /** @return string edit URL of the new record */
    public static function convert(TravelObject $o): string
    {
        $description = array_filter($o->getTranslations('description'));

        if ($o->type === ObjectType::Arrangement) {
            $tour = Tour::create([
                'agency_id' => $o->agency_id,
                'tour_operator_id' => $o->tour_operator_id,
                'group_id' => $o->group_id,
                'destination_id' => $o->destination_id,
                'title' => ['mk' => trim($o->name)],
                'tour_type' => TourType::Bus,
                'location' => trim(collect([$o->place, $o->region])->filter()->implode(', ')) ?: null,
                'country' => $o->country,
                'map_location' => $o->map_location,
                'description' => $description ?: null,
                'is_active' => false,
            ]);
            static::copyImages($o, $tour);

            return TourResource::getUrl('edit', ['record' => $tour]);
        }

        $acc = Accommodation::create([
            'agency_id' => $o->agency_id,
            'tour_operator_id' => $o->tour_operator_id,
            'group_id' => $o->group_id,
            'destination_id' => $o->destination_id,
            'name' => trim($o->name),
            'type' => $o->type === ObjectType::Apartment ? AccommodationType::Apartment : AccommodationType::Hotel,
            'stars' => $o->stars,
            'place' => $o->place,
            'region' => $o->region,
            'country' => $o->country,
            'map_location' => $o->map_location,
            'overview' => $description ?: null,
            'is_active' => false,
        ]);
        static::copyImages($o, $acc);

        return AccommodationResource::getUrl('edit', ['record' => $acc]);
    }

    public static function copyImages(TravelObject $from, $to): void
    {
        foreach ($from->images as $i => $img) {
            $to->media()->create([
                'path' => $img->path,
                'caption' => $img->caption ? ['mk' => $img->caption] : null,
                'is_main' => $img->is_main,
                'sort_order' => $i + 1,
            ]);
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\Airline;
use App\Models\Amenity;
use App\Models\DeparturePlace;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        Airline::seedDefaults();
        Amenity::seedDefaults();

        foreach (Agency::pluck('id') as $agencyId) {
            DeparturePlace::seedDefaults($agencyId);
        }
    }
}

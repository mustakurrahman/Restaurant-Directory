<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    // Order matters: restaurants need cities, cuisines and amenities to exist first.
    // Do not add WithoutModelEvents here: it would switch off automatic slug generation.
    public function run(): void
    {
        $this->call([
            CitySeeder::class,
            CuisineSeeder::class,
            AmenitySeeder::class,
            RestaurantSeeder::class,
        ]);
    }
}

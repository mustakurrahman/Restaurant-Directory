<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CitySeeder extends Seeder
{
    public function run(): void
    {
        // Edit this list to change the cities (also update RestaurantSeeder to match)
        $cities = ['New York', 'Los Angeles', 'Chicago', 'London', 'Dubai'];

        // firstOrCreate: running the seeder twice never creates duplicates
        foreach ($cities as $name) {
            City::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]);
        }
    }
}

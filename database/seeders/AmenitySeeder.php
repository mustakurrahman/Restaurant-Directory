<?php

namespace Database\Seeders;

use App\Models\Amenity;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AmenitySeeder extends Seeder
{
    public function run(): void
    {
        $amenities = [
            'Wi-Fi', 'Parking', 'Outdoor Seating', 'Wheelchair Accessible',
            'Live Music', 'Pet Friendly', 'Delivery', 'Takeaway',
            'Reservations', 'Family Friendly', 'Vegan Options', 'Full Bar',
        ];

        foreach ($amenities as $name) {
            Amenity::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]);
        }
    }
}

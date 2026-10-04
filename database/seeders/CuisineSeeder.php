<?php

namespace Database\Seeders;

use App\Models\Cuisine;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CuisineSeeder extends Seeder
{
    public function run(): void
    {
        $cuisines = [
            'Italian', 'Indian', 'Japanese', 'Mexican', 'Chinese',
            'French', 'American', 'Thai', 'Mediterranean', 'Seafood',
        ];

        foreach ($cuisines as $name) {
            Cuisine::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]);
        }
    }
}

<?php

namespace Database\Factories;

use App\Models\City;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<City>
 */
class CityFactory extends Factory
{
    public function definition(): array
    {
        // slug is left out: the HasSlug trait builds it from the name
        return [
            'name' => fake()->unique()->city(),
        ];
    }
}

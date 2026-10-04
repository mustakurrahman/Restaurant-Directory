<?php

namespace Database\Factories;

use App\Models\Cuisine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cuisine>
 */
class CuisineFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word().' cuisine',
        ];
    }
}

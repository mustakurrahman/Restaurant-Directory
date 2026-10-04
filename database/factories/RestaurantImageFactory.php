<?php

namespace Database\Factories;

use App\Models\Restaurant;
use App\Models\RestaurantImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantImage>
 */
class RestaurantImageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            // Placeholder paths: real uploads replace these in Sprint 3
            'path' => 'placeholders/gallery-'.fake()->numberBetween(1, 6).'.jpg',
            'alt_text' => fake()->randomElement(['Dining area', 'Signature dish', 'Bar and drinks']),
            'sort_order' => 0,
        ];
    }
}

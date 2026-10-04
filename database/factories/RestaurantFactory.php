<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Restaurant>
 */
class RestaurantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'The '.fake()->lastName().' Table',
            'description' => fake()->paragraph(3),
            'address' => fake()->streetAddress(),
            'city_id' => City::factory(),
            // 555-01xx numbers are reserved for fiction, so nobody gets called
            'phone' => fake()->numerify('(###) 555-01##'),
            'email' => fake()->unique()->userName().'@example.com',
            'website' => 'https://'.fake()->unique()->domainWord().'.example.com',
            'price_range' => fake()->numberBetween(1, 4),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'cover_image' => 'placeholders/restaurant-'.fake()->numberBetween(1, 8).'.jpg',
            'is_featured' => false,
            'status' => 'published',
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => 'draft']);
    }

    public function featured(): static
    {
        return $this->state(['is_featured' => true]);
    }
}

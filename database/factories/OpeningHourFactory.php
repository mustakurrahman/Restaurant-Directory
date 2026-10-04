<?php

namespace Database\Factories;

use App\Models\OpeningHour;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OpeningHour>
 */
class OpeningHourFactory extends Factory
{
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'day_of_week' => fake()->numberBetween(1, 7), // 1 = Monday ... 7 = Sunday
            'opens_at' => '11:00',
            'closes_at' => '22:00',
            'is_closed' => false,
        ];
    }

    // OpeningHour::factory()->closed() = closed all day
    public function closed(): static
    {
        return $this->state(['opens_at' => null, 'closes_at' => null, 'is_closed' => true]);
    }
}

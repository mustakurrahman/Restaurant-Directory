<?php

namespace Database\Factories;

use App\Models\Restaurant;
use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'name' => fake()->firstName().' '.fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            // Weighted towards 4 and 5 stars, like most real review sites
            'rating' => fake()->randomElement([3, 4, 4, 4, 5, 5, 5]),
            'comment' => fake()->randomElement([
                'Wonderful evening. The food arrived hot and the staff were friendly and attentive.',
                'Great value for the portion sizes. We will definitely be back with friends.',
                'The signature dish was the best I have had in years. Book ahead at the weekend!',
                'Cosy atmosphere and a short wait even when busy. Perfect for a date night.',
                'Fresh ingredients and a creative menu. The dessert was a lovely surprise.',
                'Lovely place for a family meal. The kids menu was good and service was quick.',
                'Solid food and generous portions. A little noisy at peak time, but worth it.',
                'Excellent service from start to finish. Highly recommend the daily specials.',
            ]),
            'status' => 'pending',
        ];
    }

    // Review::factory()->approved() = visible to the public
    public function approved(): static
    {
        return $this->state(['status' => 'approved']);
    }
}

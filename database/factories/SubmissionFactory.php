<?php

namespace Database\Factories;

use App\Models\Submission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Submission>
 */
class SubmissionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'restaurant_name' => fake()->company().' Kitchen',
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'cuisine' => fake()->randomElement(['Italian', 'Thai', 'Mexican', null]),
            'phone' => '+1 555 010 0199',
            'website' => 'https://example.com',
            'description' => fake()->sentence(12),
            'submitter_name' => fake()->name(),
            'submitter_email' => fake()->unique()->safeEmail(),
            'status' => 'pending',
        ];
    }
}

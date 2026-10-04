<?php

namespace Database\Factories;

use App\Models\ContactMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactMessage>
 */
class ContactMessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'subject' => fake()->randomElement(['Question about a listing', 'Wrong opening hours', 'Partnership idea', null]),
            'message' => fake()->paragraph(),
            'is_read' => false,
        ];
    }

    // ContactMessage::factory()->read()
    public function read(): static
    {
        return $this->state(['is_read' => true]);
    }
}

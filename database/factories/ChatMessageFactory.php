<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ChatMessage>
 */
class ChatMessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'  => User::factory(),
            'role'     => 'user',
            'text'     => fake()->sentence(),
            'schedule' => null,
        ];
    }

    public function assistant(): static
    {
        return $this->state(['role' => 'assistant']);
    }
}

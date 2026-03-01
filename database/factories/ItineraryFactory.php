<?php

namespace Database\Factories;

use App\Models\Itinerary;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Itinerary>
 */
class ItineraryFactory extends Factory
{
    protected $model = Itinerary::class;

    public function definition(): array
    {
        $start = fake()->numberBetween(1, 180);

        return [
            'user_id'     => User::factory(),
            'title'       => fake()->sentence(3),
            'description' => fake()->optional(0.7)->paragraph(),
            'start_date'  => now()->addDays($start)->format('Y-m-d'),
            'end_date'    => now()->addDays($start + fake()->numberBetween(1, 14))->format('Y-m-d'),
            'status'      => fake()->randomElement(['draft', 'published', 'archived']),
        ];
    }
}

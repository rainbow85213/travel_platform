<?php

namespace Database\Factories;

use App\Models\Place;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Place>
 */
class PlaceFactory extends Factory
{
    protected $model = Place::class;

    public function definition(): array
    {
        return [
            'name'          => fake()->company() . ' ' . fake()->randomElement(['Park', 'Museum', 'Tower', 'Palace', 'Temple']),
            'description'   => fake()->paragraph(),
            'address'       => fake()->address(),
            'city'          => fake()->city(),
            'country'       => fake()->country(),
            'latitude'      => fake()->latitude(),
            'longitude'     => fake()->longitude(),
            'category'      => fake()->randomElement(['attraction', 'restaurant', 'hotel', 'museum', 'cafe']),
            'thumbnail_url' => fake()->imageUrl(),
            'rating'        => fake()->randomFloat(2, 0, 5),
        ];
    }
}

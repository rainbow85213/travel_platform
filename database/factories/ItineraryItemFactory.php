<?php

namespace Database\Factories;

use App\Models\Itinerary;
use App\Models\ItineraryItem;
use App\Models\Place;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItineraryItem>
 */
class ItineraryItemFactory extends Factory
{
    protected $model = ItineraryItem::class;

    public function definition(): array
    {
        return [
            'itinerary_id'     => Itinerary::factory(),
            'place_id'         => Place::factory(),
            'day_number'       => fake()->numberBetween(1, 7),
            'sort_order'       => fake()->numberBetween(0, 10),
            'visited_at'       => fake()->optional(0.5)->dateTime(),
            'duration_minutes' => fake()->optional(0.5)->numberBetween(30, 480),
            'notes'            => fake()->optional(0.5)->paragraph(),
        ];
    }
}

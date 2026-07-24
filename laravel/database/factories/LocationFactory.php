<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\LocationCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test-only convenience. Coordinates fall inside Tekirdağ province's rough
 * bounding box so factory data looks plausible on a map.
 *
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition(): array
    {
        return [
            'title' => fake()->streetName(),
            'province_id' => 59,
            'district_id' => fake()->numberBetween(1, 11),
            'lat' => fake()->randomFloat(7, 40.6, 41.5),
            'long' => fake()->randomFloat(7, 27.0, 28.1),
            'status' => 'active',
            'category_id' => LocationCategory::factory(),
            'description' => fake()->optional()->sentence(),
        ];
    }

    public function disabled(): static
    {
        return $this->state(['status' => 'disabled']);
    }
}

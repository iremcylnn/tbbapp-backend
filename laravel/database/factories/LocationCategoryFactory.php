<?php

namespace Database\Factories;

use App\Models\LocationCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test-only convenience. Seed data (the real mock dataset) lives in
 * App\Sources\MapSeedData, not here.
 *
 * @extends Factory<LocationCategory>
 */
class LocationCategoryFactory extends Factory
{
    protected $model = LocationCategory::class;

    public function definition(): array
    {
        return [
            'title' => fake()->unique()->words(2, true),
            'status' => 'active',
        ];
    }

    public function disabled(): static
    {
        return $this->state(['status' => 'disabled']);
    }
}

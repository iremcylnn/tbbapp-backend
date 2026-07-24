<?php

namespace Database\Factories;

use App\Models\District;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test-only convenience. The real 11 districts ship in the migration and
 * App\Sources\MapSeedData.
 *
 * @extends Factory<District>
 */
class DistrictFactory extends Factory
{
    protected $model = District::class;

    public function definition(): array
    {
        return [
            'title' => fake()->unique()->city(),
            'status' => 'active',
        ];
    }

    public function disabled(): static
    {
        return $this->state(['status' => 'disabled']);
    }
}

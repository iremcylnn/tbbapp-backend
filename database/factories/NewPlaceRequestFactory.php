<?php

namespace Database\Factories;

use App\Models\NewPlaceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewPlaceRequest>
 */
class NewPlaceRequestFactory extends Factory
{
    protected $model = NewPlaceRequest::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->company(),
            'category_id' => null,
            'description' => fake()->sentence(10),
            // Tekirdağ province bounds, same as LocationFactory.
            'lat' => fake()->randomFloat(7, 40.8, 41.3),
            'long' => fake()->randomFloat(7, 26.6, 28.2),
            'status' => 'pending',
        ];
    }
}

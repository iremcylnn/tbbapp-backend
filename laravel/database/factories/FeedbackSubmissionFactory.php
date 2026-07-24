<?php

namespace Database\Factories;

use App\Models\FeedbackSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeedbackSubmission>
 */
class FeedbackSubmissionFactory extends Factory
{
    protected $model = FeedbackSubmission::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'kind' => fake()->randomElement(FeedbackSubmission::KINDS),
            'description' => fake()->sentence(10),
            'location_id' => null,
            'lat' => null,
            'long' => null,
        ];
    }
}

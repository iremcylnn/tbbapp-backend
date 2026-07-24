<?php

namespace Tests\Feature;

use App\Models\FeedbackSubmission;
use App\Models\Location;
use App\Models\LocationCategory;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\PostgresTestCase;

class FeedbackTest extends PostgresTestCase
{
    public function test_submitting_requires_login(): void
    {
        $this->postJson('/api/feedback', ['kind' => 'complaint', 'description' => 'Test'])
            ->assertUnauthorized();
    }

    public function test_citizen_can_submit_feedback(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/feedback', [
            'kind' => 'complaint',
            'description' => '  Park aydınlatması çalışmıyor.  ',
            'lat' => 40.9778,
            'long' => 27.5147,
        ]);

        $response->assertCreated()
            ->assertJsonPath('kind', 'complaint')
            ->assertJsonPath('description', 'Park aydınlatması çalışmıyor.')
            ->assertJsonPath('user_id', $user->id);

        $this->assertDatabaseCount('feedback_submissions', 1);
    }

    public function test_feedback_can_reference_an_existing_place(): void
    {
        $category = LocationCategory::factory()->create();
        $place = Location::factory()->for($category, 'category')->create();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/feedback', [
            'kind' => 'request',
            'description' => 'Buraya bank konulsun.',
            'location_id' => $place->id,
        ])->assertCreated()->assertJsonPath('location_id', $place->id);
    }

    public function test_validation_rejects_bad_input_in_turkish(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/feedback', [
            'kind' => 'rant',
            'description' => str_repeat('a', 2001),
            'location_id' => 9999,
            'lat' => 123.0,
        ])->assertStatus(422)->assertJsonValidationErrors([
            'kind' => 'kind şunlardan biri olmalıdır: complaint, request.',
            'description' => 'Açıklama 2000 karakteri geçemez.',
            'location_id' => 'Geçersiz location_id.',
            'lat' => 'lat -90 ile 90 arasında olmalıdır.',
        ]);
    }

    public function test_mine_returns_only_own_submissions_newest_first(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();

        $old = FeedbackSubmission::factory()->for($me)->create(['created_at' => now()->subDay()]);
        $new = FeedbackSubmission::factory()->for($me)->create(['created_at' => now()]);
        FeedbackSubmission::factory()->for($other)->create();

        Sanctum::actingAs($me);

        $response = $this->getJson('/api/feedback/mine');

        $response->assertOk()->assertJsonCount(2);
        $this->assertSame([$new->id, $old->id], array_column($response->json(), 'id'));
    }

    public function test_admin_listing_requires_the_key(): void
    {
        $this->getJson('/api/feedback')->assertUnauthorized();
        $this->getJson('/api/feedback', ['x-admin-key' => 'wrong-key'])->assertUnauthorized();
    }

    public function test_admin_listing_includes_public_user_info_and_filters_by_kind(): void
    {
        $user = User::factory()->create(['first_name' => 'Ayşe', 'last_name' => 'Yılmaz']);
        FeedbackSubmission::factory()->for($user)->create(['kind' => 'complaint']);
        FeedbackSubmission::factory()->for($user)->create(['kind' => 'request']);

        $all = $this->getJson('/api/feedback', ['x-admin-key' => 'test-admin-key']);
        $all->assertOk()->assertJsonCount(2)
            ->assertJsonPath('0.user.firstName', 'Ayşe');
        // The password hash must never appear anywhere in the payload.
        $this->assertStringNotContainsString('password', $all->getContent());

        $this->getJson('/api/feedback?kind=complaint', ['x-admin-key' => 'test-admin-key'])
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.kind', 'complaint');

        $this->getJson('/api/feedback?kind=bogus', ['x-admin-key' => 'test-admin-key'])
            ->assertStatus(422);
    }

    public function test_submissions_are_rate_limited(): void
    {
        Sanctum::actingAs(User::factory()->create());

        for ($i = 0; $i < 20; $i++) {
            $this->postJson('/api/feedback', ['kind' => 'request', 'description' => "Talep $i"]);
        }

        $this->postJson('/api/feedback', ['kind' => 'request', 'description' => 'Talep 21'])
            ->assertStatus(429);
    }
}

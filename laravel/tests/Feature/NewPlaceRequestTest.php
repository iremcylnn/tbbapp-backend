<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\LocationCategory;
use App\Models\MapVersion;
use App\Models\NewPlaceRequest;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\PostgresTestCase;

class NewPlaceRequestTest extends PostgresTestCase
{
    private const ADMIN = ['x-admin-key' => 'test-admin-key'];

    public function test_submitting_requires_login(): void
    {
        $this->postJson('/api/new-place-requests', ['title' => 'Yeni park'])
            ->assertUnauthorized();
    }

    public function test_citizen_can_submit_a_proposal_starting_pending(): void
    {
        $category = LocationCategory::factory()->create();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/new-place-requests', [
            'title' => '  Yeni Mahalle Parkı  ',
            'category_id' => $category->id,
            'description' => 'Buraya bir park yapılmalı.',
            'lat' => 40.9778,
            'long' => 27.5147,
        ])->assertCreated()
            ->assertJsonPath('title', 'Yeni Mahalle Parkı')
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('user_id', $user->id);
    }

    public function test_validation_rejects_bad_input_in_turkish(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/new-place-requests', [
            'title' => str_repeat('a', 201),
            'category_id' => 9999,
            'lat' => 123.0,
        ])->assertStatus(422)->assertJsonValidationErrors([
            'title' => 'title 200 karakteri geçemez.',
            'category_id' => 'Geçersiz category_id.',
            'description' => 'Açıklama zorunludur.',
            'lat' => 'lat -90 ile 90 arasında olmalıdır.',
            'long' => 'long zorunludur.',
        ]);
    }

    public function test_mine_returns_only_own_requests_newest_first(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();

        $old = NewPlaceRequest::factory()->for($me)->create(['created_at' => now()->subDay()]);
        $new = NewPlaceRequest::factory()->for($me)->create(['created_at' => now()]);
        NewPlaceRequest::factory()->for($other)->create();

        Sanctum::actingAs($me);

        $response = $this->getJson('/api/new-place-requests/mine');

        $response->assertOk()->assertJsonCount(2);
        $this->assertSame([$new->id, $old->id], array_column($response->json(), 'id'));
    }

    public function test_admin_endpoints_require_the_key(): void
    {
        $this->getJson('/api/new-place-requests')->assertUnauthorized();
        $this->patchJson('/api/new-place-requests/1', ['status' => 'approved'])->assertUnauthorized();
        $this->getJson('/api/admin/action-logs')->assertUnauthorized();
        $this->getJson('/api/new-place-requests', ['x-admin-key' => 'wrong-key'])->assertUnauthorized();
    }

    public function test_admin_listing_filters_by_status_and_includes_user_info(): void
    {
        $user = User::factory()->create(['first_name' => 'Ayşe', 'last_name' => 'Yılmaz']);
        NewPlaceRequest::factory()->for($user)->create(['status' => 'pending']);
        NewPlaceRequest::factory()->for($user)->create(['status' => 'rejected']);

        $all = $this->getJson('/api/new-place-requests', self::ADMIN);
        $all->assertOk()->assertJsonCount(2)->assertJsonPath('0.user.firstName', 'Ayşe');
        $this->assertStringNotContainsString('password', $all->getContent());

        $this->getJson('/api/new-place-requests?status=pending', self::ADMIN)
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.status', 'pending');

        $this->getJson('/api/new-place-requests?status=bogus', self::ADMIN)
            ->assertStatus(422);
    }

    public function test_approval_creates_a_location_bumps_map_version_and_logs(): void
    {
        $category = LocationCategory::factory()->create();
        $submission = NewPlaceRequest::factory()
            ->for(User::factory()->create())
            ->create(['category_id' => $category->id, 'title' => 'Onaylanan Park']);

        $versionBefore = MapVersion::current();

        $response = $this->patchJson(
            "/api/new-place-requests/{$submission->id}",
            ['status' => 'approved', 'district_id' => 1],
            self::ADMIN,
        );

        $response->assertOk()
            ->assertJsonPath('submission.status', 'approved')
            ->assertJsonPath('location.title', 'Onaylanan Park')
            ->assertJsonPath('location.district_id', 1);

        // The new locations row is a real, active, province-59 place …
        $location = Location::findOrFail($response->json('location.id'));
        $this->assertSame('active', $location->status);
        $this->assertSame(59, $location->province_id);
        $this->assertSame($category->id, $location->category_id);

        // … so the Postgres trigger bumped the version (bootstrap ETag rotates).
        $this->assertGreaterThan($versionBefore, MapVersion::current());

        // Audit log written in the same transaction as the approval.
        $this->assertDatabaseHas('admin_action_logs', [
            'action' => 'new_place_request.approved',
            'target_type' => 'NewPlaceRequest',
            'target_id' => $submission->id,
        ]);
    }

    public function test_approval_requires_district_and_a_category_from_somewhere(): void
    {
        $submission = NewPlaceRequest::factory()->create(['category_id' => null]);

        // No district_id at all → 422.
        $this->patchJson("/api/new-place-requests/{$submission->id}", ['status' => 'approved'], self::ADMIN)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['district_id' => 'Onay için district_id zorunludur.']);

        // Submission has no category and the body doesn't supply one → 422.
        $this->patchJson(
            "/api/new-place-requests/{$submission->id}",
            ['status' => 'approved', 'district_id' => 1],
            self::ADMIN,
        )->assertStatus(422)->assertJsonValidationErrors(['category_id']);

        // Both 422s left the submission untouched and created nothing.
        $this->assertSame('pending', $submission->refresh()->status);
        $this->assertDatabaseCount('locations', 0);
        $this->assertDatabaseCount('admin_action_logs', 0);
    }

    public function test_rejection_logs_and_a_second_decision_conflicts(): void
    {
        $submission = NewPlaceRequest::factory()->create();

        $this->patchJson("/api/new-place-requests/{$submission->id}", ['status' => 'rejected'], self::ADMIN)
            ->assertOk()->assertJsonPath('status', 'rejected');

        $this->assertDatabaseHas('admin_action_logs', [
            'action' => 'new_place_request.rejected',
            'target_id' => $submission->id,
        ]);
        $this->assertDatabaseCount('locations', 0);

        // The request is no longer pending — any further decision loses the claim.
        $this->patchJson(
            "/api/new-place-requests/{$submission->id}",
            ['status' => 'approved', 'district_id' => 1, 'category_id' => LocationCategory::factory()->create()->id],
            self::ADMIN,
        )->assertStatus(409);
        $this->assertDatabaseCount('locations', 0);
    }

    public function test_deciding_an_unknown_request_is_404(): void
    {
        $this->patchJson('/api/new-place-requests/9999', ['status' => 'rejected'], self::ADMIN)
            ->assertNotFound();
    }

    public function test_approved_place_appears_in_the_map_bootstrap(): void
    {
        $category = LocationCategory::factory()->create();
        $submission = NewPlaceRequest::factory()->create([
            'category_id' => $category->id,
            'title' => 'Haritaya Düşen Yer',
        ]);

        $this->patchJson(
            "/api/new-place-requests/{$submission->id}",
            ['status' => 'approved', 'district_id' => 3],
            self::ADMIN,
        )->assertOk();

        $titles = array_column($this->getJson('/api/map/bootstrap')->json('places'), 'title');
        $this->assertContains('Haritaya Düşen Yer', $titles);
    }

    public function test_admin_can_list_action_logs_newest_first(): void
    {
        $first = NewPlaceRequest::factory()->create();
        $second = NewPlaceRequest::factory()->create();

        $this->patchJson("/api/new-place-requests/{$first->id}", ['status' => 'rejected'], self::ADMIN)->assertOk();
        $this->patchJson("/api/new-place-requests/{$second->id}", ['status' => 'rejected'], self::ADMIN)->assertOk();

        $response = $this->getJson('/api/admin/action-logs', self::ADMIN);

        $response->assertOk()->assertJsonCount(2)
            ->assertJsonPath('0.target_id', $second->id)
            ->assertJsonPath('1.target_id', $first->id)
            ->assertJsonPath('0.action', 'new_place_request.rejected');
    }
}

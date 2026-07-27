<?php

namespace Tests\Feature;

use App\Models\LocationCategory;
use App\Models\NewPlaceRequest;
use App\Models\User;
use Tests\PostgresTestCase;

class AdminPanelTest extends PostgresTestCase
{
    public function test_unauthenticated_visit_redirects_to_login(): void
    {
        $this->get('/admin/new-place-requests')
            ->assertRedirect(route('admin.login'));
    }

    public function test_wrong_key_does_not_grant_access(): void
    {
        $this->post('/admin/login', ['key' => 'wrong-key'])
            ->assertSessionHasErrors('key');

        $this->get('/admin/new-place-requests')
            ->assertRedirect(route('admin.login'));
    }

    public function test_correct_key_logs_in_and_reaches_the_panel(): void
    {
        $this->post('/admin/login', ['key' => 'test-admin-key'])
            ->assertRedirect(route('admin.new-place-requests.index'));

        $this->get('/admin/new-place-requests')->assertOk();
    }

    public function test_approving_from_the_panel_creates_a_location(): void
    {
        $category = LocationCategory::factory()->create();
        $submission = NewPlaceRequest::factory()
            ->for(User::factory()->create())
            ->create(['category_id' => $category->id, 'title' => 'Panelden Onaylanan']);

        $this->withSession(['is_admin' => true])
            ->patch("/admin/new-place-requests/{$submission->id}", [
                'status' => 'approved',
                'district_id' => 1,
            ])
            ->assertRedirect();

        $this->assertSame('approved', $submission->refresh()->status);
        $this->assertDatabaseHas('locations', [
            'title' => 'Panelden Onaylanan',
            'district_id' => 1,
            'category_id' => $category->id,
        ]);
    }

    public function test_rejecting_from_the_panel_leaves_no_location(): void
    {
        $submission = NewPlaceRequest::factory()->create();

        $this->withSession(['is_admin' => true])
            ->patch("/admin/new-place-requests/{$submission->id}", ['status' => 'rejected'])
            ->assertRedirect();

        $this->assertSame('rejected', $submission->refresh()->status);
        $this->assertDatabaseCount('locations', 0);
    }

    public function test_logout_revokes_panel_access(): void
    {
        $this->withSession(['is_admin' => true])
            ->post('/admin/logout')
            ->assertRedirect(route('admin.login'));

        $this->get('/admin/new-place-requests')
            ->assertRedirect(route('admin.login'));
    }
}

<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\LocationCategory;
use App\Models\NewPlaceRequest;
use App\Models\User;
use App\Sources\LocationSource;
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

    /**
     * The dropdowns are map data, so they must come from the configured
     * LocationSource — not from Eloquent behind its back. Proof: switch to
     * the mock source, empty the districts TABLE, and the panel still offers
     * the 11 districts the map API is serving. Before this went through the
     * source, the same setup rendered an empty dropdown.
     */
    public function test_dropdowns_read_through_the_configured_source(): void
    {
        // The dropdowns only render on a pending row, so there must be one.
        NewPlaceRequest::factory()->for(User::factory()->create())->create();

        District::query()->delete();

        config(['map.source' => 'mock']);
        $this->app->forgetInstance(LocationSource::class);

        $this->withSession(['is_admin' => true])
            ->get('/admin/new-place-requests')
            ->assertOk()
            ->assertSee('Süleymanpaşa')
            ->assertSee('Ergene');
    }

    /**
     * The form's options and the validator policing them read one source, so
     * they cannot disagree: a disabled category is neither offered nor
     * accepted. (Under the old exists:locations_category,id rule it was
     * silently accepted — you could publish a location into a dead category.)
     */
    public function test_a_disabled_category_is_neither_offered_nor_accepted(): void
    {
        $disabled = LocationCategory::factory()->disabled()->create(['title' => 'Kapalı Kategori']);
        $submission = NewPlaceRequest::factory()
            ->for(User::factory()->create())
            ->create(['category_id' => null]);

        $this->withSession(['is_admin' => true])
            ->get('/admin/new-place-requests')
            ->assertOk()
            ->assertDontSee('Kapalı Kategori');

        $this->withSession(['is_admin' => true])
            ->patch("/admin/new-place-requests/{$submission->id}", [
                'status' => 'approved',
                'district_id' => 1,
                'category_id' => $disabled->id,
            ])
            ->assertSessionHasErrors('category_id');

        $this->assertSame('pending', $submission->refresh()->status);
    }

    /**
     * A named limiter is ONE bucket across every route using it (Laravel keys
     * it as md5($name.$key), with no route component). Admin login therefore
     * has its own name: an admin locked out from the shared municipal IP must
     * not spend the citizen login quota for everyone behind that address.
     */
    public function test_admin_login_does_not_share_the_citizen_auth_quota(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'yok@example.com', 'password' => 'yanlis-sifre']);
        }

        // Citizen auth is now exhausted...
        $this->postJson('/api/auth/login', ['email' => 'yok@example.com', 'password' => 'yanlis-sifre'])
            ->assertStatus(429);

        // ...but the admin panel's own counter is untouched: a wrong key still
        // gets the normal form error, not a 429.
        $this->post('/admin/login', ['key' => 'wrong-key'])
            ->assertStatus(302)
            ->assertSessionHasErrors('key');

        $this->post('/admin/login', ['key' => 'test-admin-key'])
            ->assertRedirect(route('admin.new-place-requests.index'));
    }

    /**
     * A throttled Blade form submission gets a form error, not a raw JSON
     * body rendered in the browser — the limiter's response is chosen by
     * medium (see AppServiceProvider). The API's 429 JSON is asserted above.
     */
    public function test_a_throttled_admin_login_answers_as_a_form_not_json(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->post('/admin/login', ['key' => 'wrong-key']);
        }

        $this->post('/admin/login', ['key' => 'wrong-key'])
            ->assertStatus(302)
            ->assertSessionHasErrors(['key' => 'Çok fazla deneme yapıldı, lütfen daha sonra tekrar deneyin.']);
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

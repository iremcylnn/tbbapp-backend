<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\PostgresTestCase;

class AuthTest extends PostgresTestCase
{
    private const VALID_BODY = [
        'firstName' => 'Tahir',
        'lastName' => 'Bülent',
        'email' => 'tahir@example.com',
        'password' => 'gizli-sifre-123',
    ];

    public function test_register_creates_user_and_returns_working_token(): void
    {
        $response = $this->postJson('/api/auth/register', self::VALID_BODY);

        $response->assertCreated()
            ->assertJsonStructure(['token', 'user' => ['id', 'firstName', 'lastName', 'email']])
            ->assertJsonPath('user.firstName', 'Tahir')
            ->assertJsonPath('user.email', 'tahir@example.com');

        // The password must be stored hashed, never plaintext.
        $this->assertNotSame('gizli-sifre-123', User::first()->password);

        // The returned token must actually authenticate.
        $this->withToken($response->json('token'))
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('email', 'tahir@example.com');
    }

    public function test_register_rejects_duplicate_email_in_turkish(): void
    {
        $this->postJson('/api/auth/register', self::VALID_BODY)->assertCreated();

        $this->postJson('/api/auth/register', self::VALID_BODY)
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Bu email zaten kayıtlı.');
    }

    public function test_register_validates_input(): void
    {
        $this->postJson('/api/auth/register', ['email' => 'not-an-email', 'password' => 'kısa'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['firstName', 'lastName', 'email', 'password']);
    }

    public function test_email_is_normalized_on_register_and_login(): void
    {
        $this->postJson('/api/auth/register', [
            ...self::VALID_BODY,
            'email' => '  TAHIR@Example.COM ',
        ])->assertCreated()->assertJsonPath('user.email', 'tahir@example.com');

        $this->postJson('/api/auth/login', [
            'email' => 'Tahir@EXAMPLE.com',
            'password' => 'gizli-sifre-123',
        ])->assertOk();
    }

    public function test_login_rejects_wrong_password_and_unknown_email_identically(): void
    {
        $this->postJson('/api/auth/register', self::VALID_BODY)->assertCreated();

        $wrongPassword = $this->postJson('/api/auth/login', [
            'email' => 'tahir@example.com',
            'password' => 'yanlis-sifre',
        ]);
        $unknownEmail = $this->postJson('/api/auth/login', [
            'email' => 'yok@example.com',
            'password' => 'herhangi-bir-sey',
        ]);

        // Same status, same message — the response must not reveal whether
        // the email is registered.
        $wrongPassword->assertStatus(401);
        $unknownEmail->assertStatus(401);
        $this->assertSame($wrongPassword->json('message'), $unknownEmail->json('message'));
    }

    public function test_logout_revokes_the_token(): void
    {
        $token = $this->postJson('/api/auth/register', self::VALID_BODY)->json('token');

        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();

        // Sanctum tokens live in the DB, so revocation is real — the row is
        // gone and the same token must now be refused (the old JWTs could
        // never do this). forgetGuards: within one test the framework caches
        // the authenticated guard between simulated requests; flush it so the
        // second request re-authenticates against the database.
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_protected_routes_require_a_token(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
        $this->postJson('/api/auth/logout')->assertUnauthorized();
    }

    public function test_unauthenticated_401_is_json_even_without_accept_header(): void
    {
        // Regression: plain clients (curl, no Accept: application/json) used
        // to trigger a redirect to a nonexistent login route → 500.
        $this->get('/api/auth/me')
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/json');
    }

    public function test_auth_endpoints_are_rate_limited(): void
    {
        // 20 per 15 minutes per IP; the 21st must get 429 with a Turkish message.
        for ($i = 0; $i < 20; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'x@example.com', 'password' => 'wrong-pass']);
        }

        $this->postJson('/api/auth/login', ['email' => 'x@example.com', 'password' => 'wrong-pass'])
            ->assertStatus(429)
            ->assertJsonPath('message', 'Çok fazla deneme yapıldı, lütfen daha sonra tekrar deneyin.');
    }
}

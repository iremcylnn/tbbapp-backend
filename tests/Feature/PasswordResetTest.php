<?php

namespace Tests\Feature;

use App\Mail\PasswordResetCodeMail;
use App\Models\PasswordResetCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\PostgresTestCase;

class PasswordResetTest extends PostgresTestCase
{
    private function makeUser(): User
    {
        return User::factory()->create([
            'email' => 'tahir@example.com',
            'password' => 'eski-sifre-123',
        ]);
    }

    /** Captures the plaintext code from the intercepted email. */
    private function requestCode(): string
    {
        $code = null;
        $this->postJson('/api/auth/forgot-password', ['email' => 'tahir@example.com'])->assertOk();
        Mail::assertSent(PasswordResetCodeMail::class, function (PasswordResetCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        return $code;
    }

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_forgot_password_stores_hashed_code_and_sends_mail(): void
    {
        $this->makeUser();

        $code = $this->requestCode();

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $row = PasswordResetCode::first();
        $this->assertNotSame($code, $row->code_hash);
        $this->assertTrue(Hash::check($code, $row->code_hash));
    }

    public function test_unknown_email_gets_the_same_response_and_no_mail(): void
    {
        $known = $this->postJson('/api/auth/forgot-password', ['email' => 'kimse@example.com']);

        $known->assertOk();
        Mail::assertNothingSent();
        $this->assertSame(0, PasswordResetCode::count());
    }

    public function test_reset_with_valid_code_changes_password_once(): void
    {
        $this->makeUser();
        $code = $this->requestCode();

        $this->postJson('/api/auth/reset-password', [
            'email' => 'tahir@example.com',
            'code' => $code,
            'password' => 'yepyeni-sifre-456',
        ])->assertOk();

        // New password works, old one doesn't.
        $this->postJson('/api/auth/login', ['email' => 'tahir@example.com', 'password' => 'yepyeni-sifre-456'])->assertOk();
        $this->postJson('/api/auth/login', ['email' => 'tahir@example.com', 'password' => 'eski-sifre-123'])->assertStatus(401);

        // The code is single-use: replaying it must fail.
        $this->postJson('/api/auth/reset-password', [
            'email' => 'tahir@example.com',
            'code' => $code,
            'password' => 'baska-sifre-789',
        ])->assertStatus(400);
    }

    public function test_wrong_code_is_rejected(): void
    {
        $this->makeUser();
        $code = $this->requestCode();
        $wrong = $code === '123456' ? '654321' : '123456';

        $this->postJson('/api/auth/reset-password', [
            'email' => 'tahir@example.com',
            'code' => $wrong,
            'password' => 'yepyeni-sifre-456',
        ])->assertStatus(400)->assertJsonPath('message', 'Kod geçersiz veya süresi dolmuş');
    }

    public function test_expired_code_is_rejected(): void
    {
        $user = $this->makeUser();
        $user->passwordResetCodes()->create([
            'code_hash' => Hash::make('111222'),
            'expires_at' => now()->subMinute(),
        ]);

        $this->postJson('/api/auth/reset-password', [
            'email' => 'tahir@example.com',
            'code' => '111222',
            'password' => 'yepyeni-sifre-456',
        ])->assertStatus(400);
    }

    public function test_new_code_invalidates_the_previous_one(): void
    {
        $this->makeUser();
        $first = $this->requestCode();

        Mail::fake(); // reset capture for the second request
        $this->postJson('/api/auth/forgot-password', ['email' => 'tahir@example.com'])->assertOk();

        $this->postJson('/api/auth/reset-password', [
            'email' => 'tahir@example.com',
            'code' => $first,
            'password' => 'yepyeni-sifre-456',
        ])->assertStatus(400);
    }
}

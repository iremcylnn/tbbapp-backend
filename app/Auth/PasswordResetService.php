<?php

namespace App\Auth;

use App\Mail\PasswordResetCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * The 6-digit emailed reset code lifecycle, ported from the old server:
 *
 *   - codes live 15 minutes and are stored HASHED (a DB leak leaks nothing
 *     usable);
 *   - issuing a new code invalidates older unexpired ones — otherwise several
 *     codes stay valid at once and every reset attempt must bcrypt-compare
 *     against all of them;
 *   - issue() reveals nothing about whether the email exists: the controller
 *     returns the same response either way (enumeration resistance).
 */
class PasswordResetService
{
    private const CODE_TTL_MINUTES = 15;

    public function issue(string $email): void
    {
        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            return;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->passwordResetCodes()
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->update(['used_at' => now()]);

        $user->passwordResetCodes()->create([
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
        ]);

        try {
            Mail::to($user->email)->send(new PasswordResetCodeMail($code));
        } catch (Throwable $e) {
            // Response stays identical regardless — log, don't leak.
            Log::error('Şifre sıfırlama e-postası gönderilemedi', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Attempts the reset; returns false for any invalid/expired/used code.
     * Password update and code consumption commit atomically.
     */
    public function reset(string $email, string $code, string $password): bool
    {
        $user = User::query()->where('email', $email)->first();

        $candidates = $user === null
            ? collect()
            : $user->passwordResetCodes()
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->orderByDesc('created_at')
                ->get();

        $matched = $candidates->first(
            fn ($candidate) => Hash::check($code, $candidate->code_hash)
        );

        if ($user === null || $matched === null) {
            return false;
        }

        DB::transaction(function () use ($user, $matched, $password) {
            // The 'hashed' cast on User::password bcrypts this assignment.
            $user->update(['password' => $password]);
            $matched->update(['used_at' => now()]);
        });

        return true;
    }
}

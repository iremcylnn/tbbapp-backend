<?php

namespace App\Http\Controllers;

use App\Auth\PasswordResetService;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Citizen auth — the old server's contract on Sanctum tokens:
 * register/login return {token, user:{id, firstName, lastName, email}} and the
 * app sends the token as "Authorization: Bearer ...". Unlike the old JWTs,
 * Sanctum tokens live in the database, so logout genuinely revokes them.
 */
class AuthController extends Controller
{
    /**
     * When the email is unknown we still run a bcrypt check — against this
     * throwaway hash — so "no such account" and "wrong password" take the
     * same time. Skipping the check would answer measurably faster for
     * unknown emails, letting an attacker probe which emails are registered.
     */
    private const DUMMY_HASH = '$2y$12$1IRVOo/5DIkDBW1AyucSEe3WJhCSVKD/5u5laXYuHMyZTeRCuJx9q';

    private const TOKEN_TTL_DAYS = 30;

    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::create([
            'first_name' => trim($data['firstName']),
            'last_name' => trim($data['lastName']),
            'email' => $data['email'],
            'password' => $data['password'], // 'hashed' cast bcrypts it
        ]);

        return response()->json([
            'token' => $this->issueToken($user),
            'user' => $user->toPublicArray(),
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::query()->where('email', $data['email'])->first();
        $passwordOk = Hash::check($data['password'], $user->password ?? self::DUMMY_HASH);

        if ($user === null || ! $passwordOk) {
            return response()->json(['message' => 'Email veya şifre hatalı'], 401);
        }

        return response()->json([
            'token' => $this->issueToken($user),
            'user' => $user->toPublicArray(),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user()->toPublicArray());
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Çıkış yapıldı']);
    }

    public function forgotPassword(ForgotPasswordRequest $request, PasswordResetService $service): JsonResponse
    {
        $service->issue($request->validated()['email']);

        // Same answer whether or not the email exists — see PasswordResetService.
        return response()->json(['message' => 'Bu email kayıtlıysa bir sıfırlama kodu gönderildi']);
    }

    public function resetPassword(ResetPasswordRequest $request, PasswordResetService $service): JsonResponse
    {
        $data = $request->validated();

        if (! $service->reset($data['email'], $data['code'], $data['password'])) {
            return response()->json(['message' => 'Kod geçersiz veya süresi dolmuş'], 400);
        }

        return response()->json(['message' => 'Şifre güncellendi']);
    }

    private function issueToken(User $user): string
    {
        return $user->createToken('mobile', ['*'], now()->addDays(self::TOKEN_TTL_DAYS))->plainTextToken;
    }
}

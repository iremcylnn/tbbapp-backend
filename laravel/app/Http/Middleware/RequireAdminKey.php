<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Interim admin protection: one shared secret in the x-admin-key header.
 * No user/role system yet — when real admin identities arrive this middleware
 * is the single seam to replace.
 *
 * hash_equals compares in constant time: a plain !== short-circuits on the
 * first differing character, and response timing could in theory leak how
 * much of the key an attacker has guessed.
 */
class RequireAdminKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('admin.api_key');
        $provided = $request->header('x-admin-key');

        if ($expected === '' || $provided === null || ! hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Yetkisiz'], 401);
        }

        return $next($request);
    }
}

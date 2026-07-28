<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser-facing counterpart to RequireAdminKey: gates the /admin Blade panel
 * by a session flag set at login (see Admin\AuthController), instead of a
 * per-request header. Same shared secret underneath (config('admin.api_key')),
 * different transport — the JSON API's RequireAdminKey is untouched.
 */
class RequireAdminSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->get('is_admin') !== true) {
            return redirect()->route('admin.login');
        }

        return $next($request);
    }
}

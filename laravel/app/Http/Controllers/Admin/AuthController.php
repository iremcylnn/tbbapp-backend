<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Session login for the /admin Blade panel. Same shared secret as the JSON
 * API's RequireAdminKey (config('admin.api_key')) — there is no per-admin
 * identity yet, just a key-gated session flag. See RequireAdminSession.
 */
class AuthController extends Controller
{
    public function create(): View
    {
        return view('admin.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['key' => ['required', 'string']]);

        $expected = (string) config('admin.api_key');

        if ($expected === '' || ! hash_equals($expected, $data['key'])) {
            return back()->withErrors(['key' => 'Geçersiz anahtar.']);
        }

        $request->session()->regenerate();
        $request->session()->put('is_admin', true);

        return redirect()->route('admin.new-place-requests.index');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget('is_admin');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}

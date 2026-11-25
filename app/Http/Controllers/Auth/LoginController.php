<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('ui.auth_failed'),
            ]);
        }

        $user = Auth::user();
        if ($user->status !== 'active') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => __('ui.inactive_account'),
            ]);
        }

        if ($user->two_factor_confirmed_at) {
            $remember = $request->boolean('remember');
            Auth::logout();
            $request->session()->regenerate();
            $request->session()->put('login.2fa_id', $user->id);
            $request->session()->put('login.2fa_remember', $remember);

            return redirect()->route('two-factor.login');
        }

        $request->session()->regenerate();
        session(['locale' => $user->locale ?: 'en']);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}

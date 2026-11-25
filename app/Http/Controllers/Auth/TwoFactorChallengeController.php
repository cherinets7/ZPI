<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TwoFactorChallengeController extends Controller
{
    public function create(Request $request)
    {
        abort_unless($request->session()->has('login.2fa_id'), 404);

        return view('auth.two-factor');
    }

    public function store(Request $request)
    {
        $userId = $request->session()->get('login.2fa_id');
        $user = $userId ? User::query()->where('status', 'active')->find($userId) : null;
        if (! $user || ! $user->two_factor_confirmed_at || ! $user->two_factor_secret) {
            $request->session()->forget(['login.2fa_id', 'login.2fa_remember']);

            throw ValidationException::withMessages([
                'code' => __('ui.two_factor_failed'),
            ]);
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'],
        ]);

        $code = trim($data['code']);
        $passed = Totp::verify($user->two_factor_secret, $code) || $this->useRecoveryCode($user, $code);
        if (! $passed) {
            throw ValidationException::withMessages([
                'code' => __('ui.two_factor_failed'),
            ]);
        }

        $remember = (bool) $request->session()->pull('login.2fa_remember', false);
        $request->session()->forget('login.2fa_id');
        Auth::login($user, $remember);
        $request->session()->regenerate();
        session(['locale' => $user->locale ?: 'en']);

        return redirect()->intended(route('dashboard'));
    }

    private function useRecoveryCode(User $user, string $code): bool
    {
        $codes = $user->two_factor_recovery_codes ?: [];
        foreach ($codes as $index => $hashed) {
            if (! is_string($hashed) || ! Hash::check($code, $hashed)) {
                continue;
            }
            unset($codes[$index]);
            $user->forceFill([
                'two_factor_recovery_codes' => array_values($codes),
            ])->save();

            return true;
        }

        return false;
    }
}

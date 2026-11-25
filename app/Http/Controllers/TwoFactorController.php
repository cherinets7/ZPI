<?php

namespace App\Http\Controllers;

use App\Support\Totp;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TwoFactorController extends Controller
{
    public function start(Request $request)
    {
        abort_if($request->user()->two_factor_confirmed_at, 422);

        $secret = Totp::generateSecret();
        $request->session()->put('two_factor.setup_secret', $secret);

        return back()->with('status', __('ui.two_factor_help'));
    }

    public function confirm(Request $request)
    {
        $user = $request->user();
        abort_if($user->two_factor_confirmed_at, 422);
        $secret = (string) $request->session()->get('two_factor.setup_secret', '');
        $data = $request->validate([
            'code' => ['required', 'string', 'max:10'],
        ]);

        if ($secret === '' || ! Totp::verify($secret, $data['code'])) {
            throw ValidationException::withMessages([
                'code' => __('ui.two_factor_failed'),
            ]);
        }

        $plain = [];
        $hashed = [];
        for ($i = 0; $i < 8; $i++) {
            $code = Str::upper(Str::random(10));
            $plain[] = $code;
            $hashed[] = Hash::make($code);
        }

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $hashed,
            'two_factor_confirmed_at' => now(),
        ])->save();
        $request->session()->forget('two_factor.setup_secret');

        return back()->with('recovery_codes', $plain)->with('status', __('ui.two_factor_recovery_help'));
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
        ]);

        $request->user()->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return back()->with('status', __('ui.saved'));
    }

    public static function qrDataUri(string $email, string $secret): string
    {
        return (new QRCode(new QROptions([
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'outputBase64' => true,
            'scale' => 4,
        ])))->render(Totp::provisioningUri($email, $secret));
    }
}

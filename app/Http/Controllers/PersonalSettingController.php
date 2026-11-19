<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class PersonalSettingController extends Controller
{
    public function edit(Request $request)
    {
        return view('settings.personal', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'locale' => ['required', 'in:en,am'],
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->locale = $data['locale'];
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        $user->save();
        session(['locale' => $user->locale]);

        return back()->with('status', __('ui.profile_updated'));
    }
}

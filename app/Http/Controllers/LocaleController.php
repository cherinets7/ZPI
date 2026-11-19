<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate([
            'locale' => ['required', 'in:en,am'],
        ]);

        session(['locale' => $data['locale']]);

        if ($request->user()) {
            $request->user()->forceFill(['locale' => $data['locale']])->save();
        }

        return back();
    }
}

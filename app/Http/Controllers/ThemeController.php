<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ThemeController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate([
            'theme' => ['required', 'in:light,dark'],
        ]);

        $request->user()->forceFill(['theme' => $data['theme']])->save();

        if ($request->expectsJson()) {
            return response()->json(['theme' => $data['theme']]);
        }

        return back();
    }
}

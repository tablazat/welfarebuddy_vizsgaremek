<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'token'    => 'required|string|max:500',
            'platform' => 'required|string|in:ios,android,web',
        ]);

        $request->user()->deviceTokens()->updateOrCreate(
            ['token' => $request->token],
            ['platform' => $request->platform, 'last_seen_at' => now()]
        );

        return response()->json(['message' => 'ok']);
    }

    public function destroy(Request $request)
    {
        $request->validate(['token' => 'required|string|max:500']);
        $request->user()->deviceTokens()->where('token', $request->token)->delete();
        return response()->json(['message' => 'ok']);
    }
}

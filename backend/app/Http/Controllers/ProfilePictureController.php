<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfilePictureController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'photo' => 'required|image|mimes:jpeg,png,webp|max:16384|dimensions:max_width=6000,max_height=6000',
        ]);

        $user = $request->user();

        // Töröljük a régi képet
        if ($user->profile_picture) {
            Storage::disk('public')->delete($user->profile_picture);
        }

        $path = $request->file('photo')->store('profile-pictures/' . $user->id, 'public');

        $user->update(['profile_picture' => $path]);

        return response()->json(['user' => $user->fresh()], 200);
    }

    public function destroy(Request $request)
    {
        $user = $request->user();

        if ($user->profile_picture) {
            Storage::disk('public')->delete($user->profile_picture);
            $user->update(['profile_picture' => null]);
        }

        return response()->json(['user' => $user->fresh()], 200);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Streak;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Mail\PasswordChangedMail;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    public function register(Request $request){
        $data = $request->validate([
            "name" => ["required", "string", "min:2"],
            "email" => ["required", "email", "unique:users"],
            "password" => ["required", "min:8"],
            "locale" => ["sometimes","string"],
            "terms_accepted" => ["required", "accepted"],
        ]);

        unset($data["terms_accepted"]);
        $data["terms_accepted_at"] = now();

        $user = User::create($data);
        $user->sendEmailVerificationNotification();

        $user->refresh();

        $streak = Streak::create(["user_id" => $user->id, "last_day" => now()->toDateString()]);
        $streak->refresh();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json(['token'=>$token,'user'=>$user, "streak" => $streak],201, options:JSON_UNESCAPED_UNICODE);
    }

    public function login(Request $request){
        $data = $request->validate([           
            "email" => ["required", "email", "exists:users"],
            "password" => ["required", "min:8"]
        ]);

        $user = User::where('email', $data['email'])->first();

        if(!$user || !Hash::check($data['password'], $user->password)){
            return response()->json("Bad creds", 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;
        $streak = $user->streak;

        return response()->json(['token'=>$token,'user'=>$user,"streak"=>$streak],200, options:JSON_UNESCAPED_UNICODE);
    }

    public function passwordchange(Request $request){
        $validated = $request->validate([
            "current_password" => "required|string|current_password",
            "password" => "required|string|min:8"
        ]);

        $user = $request->user();
        $user->password = $validated["password"];
        $user->save();

        Mail::to($user->email)->locale($user->locale)->send(new PasswordChangedMail($user));        

        $user->tokens()->delete(); 
        $user->save();

        return response()->json(['message' => 'Password changed successfully.']);
    }

    public function updateLvl(Request $request){
        $validated = $request->validate(["level_of_access" => "required|in:free,pro"]);
        $user = $request->user();
        $user->level_of_access = $validated['level_of_access'];
        $user->save();
        $user->refresh();
        return response()->json(['user'=>$user],200, options:JSON_UNESCAPED_UNICODE);
    }

    public function logout(Request $request){
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.'
        ],200);
    }

    public function nuke(Request $request){
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'Logged out from all devices.'
        ],200);
    }

    public function deleteAccount(Request $request){
        $user = $request->user();

        // Delete profile picture from storage
        if ($user->profile_picture) {
            Storage::disk('public')->delete($user->profile_picture);
        }

        // Delete all related data explicitly (FK constraints are RESTRICT by default)
        $user->tokens()->delete();
        $user->heartRates()->delete();
        $user->bloodPressures()->delete();
        $user->weights()->delete();
        $user->steps()->delete();
        $user->activity_users()->delete();
        $user->calorieIntakes()->delete();
        $user->waterIntakes()->delete();
        $user->sleepRecords()->delete();
        $user->deviceTokens()->delete();
        if ($user->streak) {
            $user->streak->delete();
        }

        $user->delete();

        return response()->json(['message' => 'Account deleted successfully.'], 200);
    }
}

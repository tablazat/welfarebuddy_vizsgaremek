<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * Összes felhasználó listázása (keresés, lapozás)
     */
    public function users(Request $request)
    {
        $query = User::query();

        // Keresés név vagy email alapján
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Szűrés szint alapján
        if ($level = $request->input('level')) {
            $query->where('level_of_access', $level);
        }

        $users = $query->orderByDesc('created_at')->paginate(20);

        return response()->json($users, options: JSON_UNESCAPED_UNICODE);
    }

    /**
     * Egy felhasználó adatai
     */
    public function user(Request $request, User $user)
    {
        $user->load('streak');
        $user->loadCount(['heartRates', 'bloodPressures', 'weights', 'activity_users', 'steps']);

        return response()->json(['user' => $user], options: JSON_UNESCAPED_UNICODE);
    }

    /**
     * Felhasználó szintjének módosítása (free/pro/admin)
     */
    public function updateLevel(Request $request, User $user)
    {
        $validated = $request->validate([
            'level_of_access' => 'required|in:free,pro,admin',
        ]);

        $user->level_of_access = $validated['level_of_access'];
        $user->save();
        $user->refresh();

        return response()->json(['user' => $user], options: JSON_UNESCAPED_UNICODE);
    }

    /**
     * Felhasználó törlése
     */
    public function deleteUser(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => __('messages.admin.self_delete_blocked')], 422);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => __('messages.admin.user_deleted')]);
    }

    /**
     * Összesített statisztikák az admin dashboardhoz
     */
    public function stats()
    {
        return response()->json([
            'total_users'    => User::count(),
            'free_users'     => User::where('level_of_access', 'free')->count(),
            'pro_users'      => User::where('level_of_access', 'pro')->count(),
            'admin_users'    => User::where('level_of_access', 'admin')->count(),
            'verified_users' => User::whereNotNull('email_verified_at')->count(),
            'recent_users'   => User::where('created_at', '>=', now()->subDays(7))->count(),
        ], options: JSON_UNESCAPED_UNICODE);
    }
}

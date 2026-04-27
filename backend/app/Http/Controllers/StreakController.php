<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;

class StreakController extends Controller
{
    private const COOLDOWN_DAYS = 30;

    /**
     * Returns current streak state plus freeze availability for the Pro feature.
     */
    public function status(Request $request)
    {
        $user   = $request->user();
        $streak = $user->streak;

        $isPro = $user->level_of_access === 'pro' || $user->level_of_access === 'admin';

        if (! $streak) {
            return response()->json([
                'streak'             => null,
                'is_pro'             => $isPro,
                'can_freeze'         => false,
                'cooldown_days_left' => 0,
                'last_freeze_at'     => null,
                'needs_freeze'       => false,
            ]);
        }

        $today       = Carbon::now()->startOfDay();
        $lastDate    = Carbon::parse($streak->last_day)->startOfDay();
        $diffFromLast = $lastDate->diffInDays($today);
        $needsFreeze = $diffFromLast === 2; // exactly 1 day missed (yesterday gap)

        $cooldownLeft = 0;
        if ($streak->last_freeze_at) {
            $lastFreeze = Carbon::parse($streak->last_freeze_at)->startOfDay();
            $elapsed    = $lastFreeze->diffInDays($today);
            $cooldownLeft = max(0, self::COOLDOWN_DAYS - $elapsed);
        }

        $canFreeze = $isPro && $needsFreeze && $cooldownLeft === 0;

        return response()->json([
            'streak'             => $streak,
            'is_pro'             => $isPro,
            'can_freeze'         => $canFreeze,
            'cooldown_days_left' => $cooldownLeft,
            'last_freeze_at'     => $streak->last_freeze_at,
            'needs_freeze'       => $needsFreeze,
        ]);
    }

    /**
     * Consumes a freeze to save a broken streak (exactly 1 missed day).
     * Sets last_day to yesterday so the next entry today continues the streak.
     * 30 day cooldown between freezes.
     */
    public function freeze(Request $request)
    {
        $user = $request->user();

        if ($user->level_of_access !== 'pro' && $user->level_of_access !== 'admin') {
            return response()->json([
                'code'    => 'not_pro',
                'message' => 'Streak freeze is a Pro feature.',
            ], 403);
        }

        $streak = $user->streak;
        if (! $streak) {
            return response()->json([
                'code'    => 'no_streak',
                'message' => 'No active streak.',
            ], 404);
        }

        $today    = Carbon::now()->startOfDay();
        $lastDate = Carbon::parse($streak->last_day)->startOfDay();
        $diff     = $lastDate->diffInDays($today);

        if ($diff < 2) {
            return response()->json([
                'code'    => 'not_needed',
                'message' => 'Streak is not broken.',
            ], 422);
        }

        if ($diff > 2) {
            return response()->json([
                'code'    => 'too_late',
                'message' => 'Streak already lost (more than 1 day missed).',
            ], 422);
        }

        if ($streak->last_freeze_at) {
            $lastFreeze = Carbon::parse($streak->last_freeze_at)->startOfDay();
            $elapsed    = $lastFreeze->diffInDays($today);
            if ($elapsed < self::COOLDOWN_DAYS) {
                return response()->json([
                    'code'               => 'cooldown',
                    'message'            => 'Freeze is on cooldown.',
                    'cooldown_days_left' => self::COOLDOWN_DAYS - $elapsed,
                ], 422);
            }
        }

        $streak->last_day        = $today->copy()->subDay();
        $streak->last_freeze_at  = $today;
        $streak->save();

        return response()->json([
            'code'    => 'frozen',
            'message' => 'Streak saved.',
            'streak'  => $streak->fresh(),
        ]);
    }
}

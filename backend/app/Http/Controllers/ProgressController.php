<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;


class ProgressController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $weightStart = $user->weights()->orderBy('recorded_at', 'asc')->first();
        $weightCurrent = $user->weights()->orderBy('recorded_at', 'desc')->first();

        $weightStartVal   = $weightStart?->weight !== null ? (float) $weightStart->weight : null;
        $weightCurrentVal = $weightCurrent?->weight !== null ? (float) $weightCurrent->weight : null;
        $weightDelta = ($weightStartVal !== null && $weightCurrentVal !== null)
            ? round($weightCurrentVal - $weightStartVal, 1)
            : null;

        $heightM = $user->height_cm ? $user->height_cm / 100 : null;
        $bmiFor = fn (?float $w) => ($heightM && $w) ? round($w / ($heightM * $heightM), 1) : null;

        $streak = $user->streak;
        $entriesTotal = $user->heartRates()->count()
            + $user->bloodPressures()->count()
            + $user->weights()->count()
            + $user->steps()->count()
            + $user->calorieIntakes()->count()
            + $user->waterIntakes()->count()
            + $user->sleepRecords()->count()
            + $user->activity_users()->count();

        return response()->json([
            'weight_start'    => $weightStartVal,
            'weight_current'  => $weightCurrentVal,
            'weight_delta'    => $weightDelta,
            'bmi_start'       => $bmiFor($weightStartVal),
            'bmi_current'     => $bmiFor($weightCurrentVal),
            'streak_current'  => $streak?->days ?? 0,
            'streak_max'      => (int) ($user->max_days ?? 0),
            'days_active'     => $user->created_at ? (int) $user->created_at->diffInDays(now()) : 0,
            'entries_total'   => $entriesTotal,
        ]);
    }
}

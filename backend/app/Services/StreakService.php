<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;

class StreakService
{
    
    public static function update(User $user, $recordedAt): void
    {
        $streak = $user->streak;
        if (! $streak) return;

        $recordedDate = Carbon::parse($recordedAt)->startOfDay();
        $lastDate     = Carbon::parse($streak->last_day)->startOfDay();

        if ($recordedDate < $lastDate) return;

        $diffInDays = $lastDate->diffInDays($recordedDate);

        if ($diffInDays > 1) {
            $streak->last_day = $recordedAt;
            $streak->days = 1;
            $streak->save();
        } elseif ($diffInDays == 1) {
            $streak->last_day = $recordedAt;
            $streak->days += 1;
            if ($streak->days > $user->max_days) {
                $user->max_days = $streak->days;
                $user->save();
            }
            $streak->save();
        }
    }
}

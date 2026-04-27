<?php

namespace App\Http\Controllers;

use App\Events\UploadCreated;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HealthSyncController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'heart_rates'               => 'nullable|array|max:500',
            'heart_rates.*.heart_rate'  => 'required_with:heart_rates|integer|min:1|max:300',
            'heart_rates.*.recorded_at' => 'required_with:heart_rates|date',

            'blood_pressures'               => 'nullable|array|max:500',
            'blood_pressures.*.systolic'    => 'required_with:blood_pressures|integer|min:1|max:300',
            'blood_pressures.*.diastolic'   => 'required_with:blood_pressures|integer|min:1|max:200',
            'blood_pressures.*.recorded_at' => 'required_with:blood_pressures|date',

            'weights'               => 'nullable|array|max:500',
            'weights.*.weight'      => 'required_with:weights|numeric|min:1|max:600',
            'weights.*.recorded_at' => 'required_with:weights|date',

            'steps'               => 'nullable|array|max:500',
            'steps.*.steps'       => 'required_with:steps|integer|min:0|max:100000',
            'steps.*.recorded_at' => 'required_with:steps|date',

            'exercises'                => 'nullable|array|max:200',
            'exercises.*.activity_id'  => 'required_with:exercises|exists:activities,id',
            'exercises.*.begin'        => 'required_with:exercises|date_format:Y-m-d H:i:s',
            'exercises.*.end'          => 'required_with:exercises|date_format:Y-m-d H:i:s',
        ]);

        $user   = $request->user();
        $counts = [
            'heart_rates'    => 0,
            'blood_pressures'=> 0,
            'weights'        => 0,
            'steps'          => 0,
            'exercises'      => 0,
        ];

        DB::transaction(function () use ($request, $user, &$counts) {
            foreach ($request->heart_rates ?? [] as $item) {
                $user->heartRates()->create([
                    'heart_rate'  => $item['heart_rate'],
                    'recorded_at' => $item['recorded_at'],
                ]);
                $counts['heart_rates']++;
            }

            foreach ($request->blood_pressures ?? [] as $item) {
                $user->bloodPressures()->create([
                    'systolic'    => $item['systolic'],
                    'diastolic'   => $item['diastolic'],
                    'recorded_at' => $item['recorded_at'],
                ]);
                $counts['blood_pressures']++;
            }

            foreach ($request->weights ?? [] as $item) {
                $user->weights()->create([
                    'weight'      => $item['weight'],
                    'recorded_at' => $item['recorded_at'],
                ]);
                $counts['weights']++;
            }

            foreach ($request->steps ?? [] as $item) {
                $date     = Carbon::parse($item['recorded_at'])->toDateString();
                $existing = $user->steps()->lockForUpdate()->where('recorded_at', $date)->first();
                if ($existing) {
                    $existing->steps += (int) $item['steps'];
                    $existing->save();
                } else {
                    $user->steps()->create([
                        'steps'       => (int) $item['steps'],
                        'recorded_at' => $date,
                    ]);
                }
                $counts['steps']++;
            }

            foreach ($request->exercises ?? [] as $item) {
                $user->activity_users()->create([
                    'activity_id' => $item['activity_id'],
                    'begin'       => $item['begin'],
                    'end'         => $item['end'],
                ]);
                $counts['exercises']++;
            }
        });

        // Streak csak akkor léphet előre, ha a batchben van a MAI napra szóló
        // új rekord. Régi, HealthKit/Health Connect-ből visszamenőleg importált
        // adat nem számít „aktív engagement" napnak – különben egy új user
        // ránézésre 7–30 napos streakkel nyitna, csupán a historikus import miatt.
        $today  = now()->toDateString();
        $hasTodayEntry = $this->batchHasToday($request, $today);

        $total = array_sum($counts);
        if ($total > 0) {
            if ($hasTodayEntry) {
                DB::transaction(function () use ($user, $today) {
                    $streak = $user->streak()->lockForUpdate()->first();
                    if ($streak) {
                        $yesterday = now()->subDay()->toDateString();
                        $lastDay   = Carbon::parse($streak->last_day)->toDateString();

                        if ($lastDay !== $today) {
                            if ($lastDay === $yesterday) {
                                $streak->days++;
                            } else {
                                $streak->days = 1;
                            }
                            if ($streak->days > $user->max_days) {
                                $user->max_days = $streak->days;
                                $user->save();
                            }
                            $streak->last_day = $today;
                            $streak->save();
                        }
                    }
                });
            }

            broadcast(new UploadCreated($user->id, [
                'type'   => 'health_sync_complete',
                'counts' => $counts,
            ]));
        }

        return response()->json(['message' => 'ok', 'counts' => $counts], 201);
    }

    /**
     * Van-e a batchben legalább egy rekord a mai napra? Ha csak historikus
     * adat jön (pl. első HealthKit import), a streaket nem mozgatjuk.
     */
    private function batchHasToday(Request $request, string $today): bool
    {
        $fields = ['heart_rates', 'blood_pressures', 'weights', 'steps'];
        foreach ($fields as $f) {
            foreach ($request->input($f, []) as $item) {
                if (!empty($item['recorded_at'])
                    && Carbon::parse($item['recorded_at'])->toDateString() === $today) {
                    return true;
                }
            }
        }
        foreach ($request->input('exercises', []) as $item) {
            if (!empty($item['begin'])
                && Carbon::parse($item['begin'])->toDateString() === $today) {
                return true;
            }
        }
        return false;
    }
}

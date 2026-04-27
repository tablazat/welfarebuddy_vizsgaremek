<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\HeartRate;
use App\Models\BloodPressure;
use App\Models\Weight;
use App\Models\Streak;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class HealthDataSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();
        if (!$user) {
            $this->command->warn('Nincs user az adatbázisban!');
            return;
        }

        // ~5 hónap: október 20 - március 20
        $startDate = Carbon::parse('2025-10-20');
        $endDate = Carbon::parse('2026-03-20');

        $baseHR = 72;
        $baseSystolic = 120;
        $baseDiastolic = 78;
        $baseWeight = 82.0;

        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            // Szívritmus - naponta 1-2 mérés
            $hrCount = rand(1, 2);
            for ($i = 0; $i < $hrCount; $i++) {
                $hour = $i === 0 ? rand(7, 9) : rand(18, 22);
                $minute = rand(0, 59);

                // Szezonális hatás: télen kicsit alacsonyabb
                $seasonal = $currentDate->month >= 11 || $currentDate->month <= 2 ? -2 : 2;
                $hr = $baseHR + $seasonal + rand(-8, 12);
                $hr = max(55, min(105, $hr));

                HeartRate::create([
                    'user_id' => $user->id,
                    'heart_rate' => $hr,
                    'recorded_at' => $currentDate->copy()->setTime($hour, $minute),
                ]);
            }

            // Vérnyomás - naponta 1 mérés (néha kimarad)
            if (rand(1, 100) <= 85) {
                $hour = rand(7, 10);
                $minute = rand(0, 59);

                $daysPassed = $startDate->diffInDays($currentDate);
                $improvement = min(5, $daysPassed * 0.03);

                $systolic = round($baseSystolic - $improvement + rand(-8, 10));
                $diastolic = round($baseDiastolic - ($improvement * 0.5) + rand(-5, 7));
                $systolic = max(100, min(150, $systolic));
                $diastolic = max(60, min(95, $diastolic));

                BloodPressure::create([
                    'user_id' => $user->id,
                    'systolic' => $systolic,
                    'diastolic' => $diastolic,
                    'recorded_at' => $currentDate->copy()->setTime($hour, $minute),
                ]);
            }

            // Testsúly - hetente 2-3 mérés
            if (in_array($currentDate->dayOfWeek, [Carbon::MONDAY, Carbon::WEDNESDAY, Carbon::SATURDAY]) || rand(1, 100) <= 10) {
                $hour = rand(6, 8);
                $minute = rand(0, 30);

                $daysPassed = $startDate->diffInDays($currentDate);
                $trend = $daysPassed * (-4.0 / 150);
                $fluctuation = (rand(-10, 10) / 10);
                $weight = round($baseWeight + $trend + $fluctuation, 1);
                $weight = max(70.0, min(90.0, $weight));

                Weight::create([
                    'user_id' => $user->id,
                    'weight' => $weight,
                    'recorded_at' => $currentDate->copy()->setTime($hour, $minute),
                ]);
            }

            $currentDate->addDay();
        }

        // Streak
        Streak::updateOrCreate(
            ['user_id' => $user->id],
            ['last_day' => '2026-03-20', 'days' => 7]
        );

        $hrCount = HeartRate::where('user_id', $user->id)->count();
        $bpCount = BloodPressure::where('user_id', $user->id)->count();
        $wCount = Weight::where('user_id', $user->id)->count();

        $this->command->info("Seeder kész! HR: {$hrCount}, BP: {$bpCount}, Weight: {$wCount}");
    }
}

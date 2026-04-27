<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\HeartRate;
use App\Models\BloodPressure;
use App\Models\Weight;
use App\Models\Step;
use App\Models\CalorieIntake;
use App\Models\WaterIntake;
use App\Models\SleepRecord;
use App\Models\Streak;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * 10 free + 5 pro teszt user realisztikus 7 hónapos health adatokkal.
 * Futtatás: php artisan db:seed --class=TestUserSeeder
 * Újrafuttatáshoz előtte törlendők a test-* userek.
 */
class TestUserSeeder extends Seeder
{
    public function run(): void
    {
        $profiles = [
            // free-1 ... free-10
            ['name' => 'Kovács Anna',     'email' => 'free-1@welfarebuddy.test',  'tier' => 'free', 'height' => 168, 'baseWeight' => 62.0, 'baseHR' => 68, 'baseSys' => 118, 'baseDia' => 76],
            ['name' => 'Nagy Bence',      'email' => 'free-2@welfarebuddy.test',  'tier' => 'free', 'height' => 182, 'baseWeight' => 85.0, 'baseHR' => 72, 'baseSys' => 122, 'baseDia' => 80],
            ['name' => 'Tóth Dóra',       'email' => 'free-3@welfarebuddy.test',  'tier' => 'free', 'height' => 165, 'baseWeight' => 58.0, 'baseHR' => 66, 'baseSys' => 115, 'baseDia' => 74],
            ['name' => 'Szabó Gergő',     'email' => 'free-4@welfarebuddy.test',  'tier' => 'free', 'height' => 178, 'baseWeight' => 78.0, 'baseHR' => 70, 'baseSys' => 120, 'baseDia' => 78],
            ['name' => 'Horváth Emese',   'email' => 'free-5@welfarebuddy.test',  'tier' => 'free', 'height' => 170, 'baseWeight' => 65.0, 'baseHR' => 74, 'baseSys' => 125, 'baseDia' => 82],
            ['name' => 'Varga Máté',      'email' => 'free-6@welfarebuddy.test',  'tier' => 'free', 'height' => 175, 'baseWeight' => 73.0, 'baseHR' => 69, 'baseSys' => 119, 'baseDia' => 77],
            ['name' => 'Kiss Réka',       'email' => 'free-7@welfarebuddy.test',  'tier' => 'free', 'height' => 162, 'baseWeight' => 55.0, 'baseHR' => 65, 'baseSys' => 112, 'baseDia' => 72],
            ['name' => 'Balogh Tamás',    'email' => 'free-8@welfarebuddy.test',  'tier' => 'free', 'height' => 185, 'baseWeight' => 92.0, 'baseHR' => 76, 'baseSys' => 128, 'baseDia' => 84],
            ['name' => 'Molnár Zsófia',   'email' => 'free-9@welfarebuddy.test',  'tier' => 'free', 'height' => 172, 'baseWeight' => 68.0, 'baseHR' => 71, 'baseSys' => 121, 'baseDia' => 79],
            ['name' => 'Farkas Dániel',   'email' => 'free-10@welfarebuddy.test', 'tier' => 'free', 'height' => 180, 'baseWeight' => 82.0, 'baseHR' => 73, 'baseSys' => 123, 'baseDia' => 81],
            // pro-1 ... pro-5
            ['name' => 'Pro Péter',       'email' => 'pro-1@welfarebuddy.test',   'tier' => 'pro',  'height' => 178, 'baseWeight' => 76.0, 'baseHR' => 64, 'baseSys' => 116, 'baseDia' => 74],
            ['name' => 'Pro Bianka',      'email' => 'pro-2@welfarebuddy.test',   'tier' => 'pro',  'height' => 170, 'baseWeight' => 63.0, 'baseHR' => 62, 'baseSys' => 114, 'baseDia' => 72],
            ['name' => 'Pro Csaba',       'email' => 'pro-3@welfarebuddy.test',   'tier' => 'pro',  'height' => 183, 'baseWeight' => 81.0, 'baseHR' => 66, 'baseSys' => 118, 'baseDia' => 76],
            ['name' => 'Pro Emma',        'email' => 'pro-4@welfarebuddy.test',   'tier' => 'pro',  'height' => 167, 'baseWeight' => 60.0, 'baseHR' => 63, 'baseSys' => 113, 'baseDia' => 73],
            ['name' => 'Pro Gábor',       'email' => 'pro-5@welfarebuddy.test',   'tier' => 'pro',  'height' => 176, 'baseWeight' => 74.0, 'baseHR' => 65, 'baseSys' => 115, 'baseDia' => 75],
        ];

        $start = Carbon::parse('2025-09-23');
        $end   = Carbon::parse('2026-04-23');

        foreach ($profiles as $p) {
            $user = User::updateOrCreate(
                ['email' => $p['email']],
                [
                    'name'              => $p['name'],
                    'password'          => Hash::make('password123'),
                    'email_verified_at' => now(),
                    'level_of_access'   => $p['tier'],
                    'height_cm'         => $p['height'],
                    'step_goal_daily'   => 10000,
                    'water_goal_ml'     => 2500,
                    'locale'            => 'hu',
                ]
            );

            $this->seedUserData($user, $p, $start, $end);
        }

        $total = count($profiles);
        $this->command->info("TestUserSeeder kész: {$total} user (10 free + 5 pro), jelszó: password123");
    }

    private function seedUserData(User $user, array $p, Carbon $start, Carbon $end): void
    {
        $proUser = $p['tier'] === 'pro';
        $cur = $start->copy();

        while ($cur->lte($end)) {
            // Pro userek következetesebbek, gyakoribb a mérésük
            $logChance = $proUser ? 92 : 72;
            if (rand(1, 100) > $logChance) { $cur->addDay(); continue; }

            // Szívritmus – 1-2 mérés
            $hrCount = $proUser ? rand(2, 3) : rand(1, 2);
            for ($i = 0; $i < $hrCount; $i++) {
                $hour = $i === 0 ? rand(7, 10) : rand(17, 22);
                $hr = $p['baseHR'] + rand(-8, 12);
                $hr = max(50, min(110, $hr));
                HeartRate::create([
                    'user_id'     => $user->id,
                    'heart_rate'  => $hr,
                    'recorded_at' => $cur->copy()->setTime($hour, rand(0, 59)),
                ]);
            }

            // Vérnyomás – 1 mérés (néha kimarad)
            if (rand(1, 100) <= ($proUser ? 85 : 55)) {
                $sys = $p['baseSys'] + rand(-8, 10);
                $dia = $p['baseDia'] + rand(-6, 8);
                BloodPressure::create([
                    'user_id'     => $user->id,
                    'systolic'    => max(95, min(160, $sys)),
                    'diastolic'   => max(60, min(100, $dia)),
                    'recorded_at' => $cur->copy()->setTime(rand(7, 10), rand(0, 59)),
                ]);
            }

            // Testsúly – hetente 2-3
            if (in_array($cur->dayOfWeek, [Carbon::MONDAY, Carbon::WEDNESDAY, Carbon::SATURDAY])) {
                $trend = $proUser ? ($start->diffInDays($cur) * -0.015) : 0;
                $w = $p['baseWeight'] + $trend + (rand(-10, 10) / 10);
                Weight::create([
                    'user_id'     => $user->id,
                    'weight'      => round(max(45, min(150, $w)), 1),
                    'recorded_at' => $cur->copy()->setTime(rand(6, 9), rand(0, 59)),
                ]);
            }

            // Lépések – naponta (steps tábla `recorded_at` date típusú, csak nap)
            $steps = $proUser ? rand(6000, 14000) : rand(2500, 11000);
            Step::create([
                'user_id'     => $user->id,
                'steps'       => $steps,
                'recorded_at' => $cur->copy()->toDateString(),
            ]);

            // Kalória – naponta 1 összesítő
            if (rand(1, 100) <= ($proUser ? 80 : 45)) {
                $kcal = $proUser ? rand(1800, 2600) : rand(1400, 3200);
                CalorieIntake::create([
                    'user_id'     => $user->id,
                    'data'        => $kcal,
                    'recorded_at' => $cur->copy()->setTime(20, rand(0, 59)),
                ]);
            }

            // Víz – 2-5 adag
            if (rand(1, 100) <= ($proUser ? 88 : 50)) {
                $drinks = rand(2, 5);
                for ($i = 0; $i < $drinks; $i++) {
                    WaterIntake::create([
                        'user_id'     => $user->id,
                        'amount_ml'   => [250, 330, 500, 750][rand(0, 3)],
                        'recorded_at' => $cur->copy()->setTime(rand(7, 22), rand(0, 59)),
                    ]);
                }
            }

            // Alvás – napi 1 rekord az előző éjszakára
            if (rand(1, 100) <= ($proUser ? 90 : 55)) {
                $hours = round(rand(50, 95) / 10, 1); // 5.0-9.5
                SleepRecord::create([
                    'user_id'     => $user->id,
                    'hours'       => $hours,
                    'quality'     => rand(2, 5),
                    'recorded_at' => $cur->copy()->setTime(7, rand(0, 30)),
                ]);
            }

            $cur->addDay();
        }

        // Streak – pro userek hosszabb sorozattal
        $streakDays = $proUser ? rand(30, 90) : rand(3, 25);
        $maxDays    = max($streakDays, $proUser ? rand(80, 150) : rand(10, 40));

        Streak::updateOrCreate(
            ['user_id' => $user->id],
            ['days' => $streakDays, 'last_day' => $end->format('Y-m-d')]
        );
        $user->update(['max_days' => $maxDays]);
    }
}

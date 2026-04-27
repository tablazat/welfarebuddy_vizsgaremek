<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Activity_User;
use App\Models\BloodPressure;
use App\Models\HeartRate;
use App\Models\SleepRecord;
use App\Models\Step;
use App\Models\User;
use App\Models\WaterIntake;
use App\Models\Weight;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * @see app/Http/Controllers/ExportController.php
 * @see CLAUDE.md session 19, 16. rész — steps.data → steps.steps + activity:id,name → name_hu/en/de bug fix
 */
class ExportTest extends TestCase
{
    use RefreshDatabase;

    private function seedHealthData(User $user): void
    {
        HeartRate::create([
            'user_id' => $user->id, 'heart_rate' => 75, 'recorded_at' => now()->subDays(2),
        ]);
        BloodPressure::create([
            'user_id' => $user->id, 'systolic' => 120, 'diastolic' => 80, 'recorded_at' => now()->subDays(2),
        ]);
        Weight::create([
            'user_id' => $user->id, 'weight' => 75.5, 'recorded_at' => now()->subDays(2),
        ]);
        Step::create([
            'user_id' => $user->id, 'steps' => 8500, 'recorded_at' => now()->subDay()->toDateString(),
        ]);
        $activity = Activity::firstOrCreate(
            ['type' => 'running'],
            ['name_en' => 'Running', 'name_hu' => 'Futás', 'name_de' => 'Laufen']
        );
        Activity_User::create([
            'user_id' => $user->id, 'activity_id' => $activity->id,
            'begin' => now()->subDay()->setTime(7, 0),
            'end'   => now()->subDay()->setTime(7, 30),
        ]);
        WaterIntake::create([
            'user_id' => $user->id, 'amount_ml' => 500, 'recorded_at' => now()->subHours(3),
        ]);
        SleepRecord::create([
            'user_id' => $user->id, 'hours' => 7.5, 'quality' => 4, 'recorded_at' => now()->subHours(8),
        ]);
    }

    public function test_export_json_returns_all_user_data_with_correct_structure(): void
    {
        $user = User::factory()->create();
        $this->seedHealthData($user);
        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/export/json');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonStructure([
                'exported_at',
                'user' => ['name', 'email', 'height_cm', 'locale'],
                'heart_rates', 'blood_pressures', 'weights', 'steps',
                'exercises', 'calorie_intakes', 'water_intakes', 'sleep_records',
            ]);

        $body = $response->json();
        $this->assertCount(1, $body['heart_rates']);
        $this->assertSame(75, (int) $body['heart_rates'][0]['heart_rate']);
        $this->assertCount(1, $body['steps']);
        $this->assertSame(8500, (int) $body['steps'][0]['steps'], 'steps oszlop neve `steps`, nem `data`');
        $this->assertCount(1, $body['exercises']);
        $this->assertNotNull($body['exercises'][0]['activity'] ?? null, 'activity reláció betöltődik');
        // JSON-ban a teljes activity objektum kijön mind a 3 nyelvvel — frontend választ
        $this->assertSame('Running', $body['exercises'][0]['activity']['name_en']);
        $this->assertSame('Futás',   $body['exercises'][0]['activity']['name_hu']);
        $this->assertSame('Laufen',  $body['exercises'][0]['activity']['name_de']);
    }

    /**
     * @see CLAUDE.md session 19, 17. rész — CSV exercise locale-aware
     */
    public function test_export_csv_exercise_uses_user_locale_for_activity_name(): void
    {
        $hu = User::factory()->create(['locale' => 'hu']);
        $en = User::factory()->create(['locale' => 'en']);
        $de = User::factory()->create(['locale' => 'de']);
        $this->seedHealthData($hu);
        $this->seedHealthData($en);
        $this->seedHealthData($de);

        $extract = function (User $u): string {
            Sanctum::actingAs($u, ['*']);
            $resp = $this->get('/export/csv');
            $tmp = tempnam(sys_get_temp_dir(), 'wb-');
            file_put_contents($tmp, $resp->getContent());
            $zip = new \ZipArchive();
            $zip->open($tmp);
            $csv = $zip->getFromName('exercises.csv');
            $zip->close();
            @unlink($tmp);
            return $csv;
        };

        $this->assertStringContainsString('Futás',   $extract($hu), 'hu locale → magyar activity név');
        $this->assertStringContainsString('Running', $extract($en), 'en locale → angol activity név');
        $this->assertStringContainsString('Laufen',  $extract($de), 'de locale → német activity név');
    }

    public function test_export_csv_returns_zip_with_8_csv_files(): void
    {
        $user = User::factory()->create();
        $this->seedHealthData($user);
        Sanctum::actingAs($user, ['*']);

        $response = $this->get('/export/csv');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/zip');

        $tmpFile = tempnam(sys_get_temp_dir(), 'wb-export-test-');
        file_put_contents($tmpFile, $response->getContent());

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($tmpFile) === true, 'response valid ZIP');

        $expected = [
            'heart_rates.csv', 'blood_pressures.csv', 'weights.csv', 'steps.csv',
            'exercises.csv', 'calorie_intakes.csv', 'water_intakes.csv', 'sleep_records.csv',
        ];
        foreach ($expected as $name) {
            $this->assertNotFalse($zip->locateName($name), "ZIP-ben legyen: $name");
        }

        // steps.csv tartalom: header + 1 sor a 8500-as értékkel
        $stepsCsv = $zip->getFromName('steps.csv');
        $this->assertStringContainsString('8500', $stepsCsv, 'steps érték a CSV-ben');

        $zip->close();
        @unlink($tmpFile);
    }

    public function test_admin_can_export_other_user_data(): void
    {
        $admin  = User::factory()->create(['level_of_access' => 'admin']);
        $target = User::factory()->create();
        $this->seedHealthData($target);
        Sanctum::actingAs($admin, ['*']);

        $response = $this->getJson("/admin/users/{$target->id}/export/json");

        $response->assertStatus(200);
        $body = $response->json();
        $this->assertSame($target->email, $body['user']['email']);
        $this->assertCount(1, $body['heart_rates']);
    }

    public function test_non_admin_cannot_export_other_user_data(): void
    {
        $alice  = User::factory()->create(['level_of_access' => 'free']);
        $target = User::factory()->create();
        Sanctum::actingAs($alice, ['*']);

        $response = $this->getJson("/admin/users/{$target->id}/export/json");

        $response->assertStatus(403);
    }

    /**
     * @see CLAUDE.md session 19, 7. rész — safeGet try-catch try minden lekérdezésen
     */
    public function test_export_continues_when_user_has_no_data(): void
    {
        // Új user, semmi adat
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/export/json');

        $response->assertStatus(200);
        $body = $response->json();
        $this->assertSame([], $body['heart_rates']);
        $this->assertSame([], $body['steps']);
        $this->assertSame([], $body['sleep_records']);
        $this->assertSame($user->email, $body['user']['email']);
    }
}

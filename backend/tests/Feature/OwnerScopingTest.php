<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Activity_User;
use App\Models\BloodPressure;
use App\Models\CalorieIntake;
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
 * Owner-scoping security regression: az `$user->relation()->findOrFail($id)` minta
 * minden adatmódosító endpointon — Alice nem férhet hozzá Bob rekordjaihoz, 404 kell.
 *
 * @see TODO.md Phase 3
 */
class OwnerScopingTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;
    private User $bob;

    protected function setUp(): void
    {
        parent::setUp();
        $this->alice = User::factory()->create();
        $this->bob   = User::factory()->create();
    }

    private function loginAlice(): void
    {
        Sanctum::actingAs($this->alice, ['*']);
    }

    public function test_user_a_cannot_delete_user_b_heart_rate(): void
    {
        $hr = HeartRate::create([
            'user_id'     => $this->bob->id,
            'heart_rate'  => 75,
            'recorded_at' => now(),
        ]);

        $this->loginAlice();
        $this->deleteJson("/heart-rates/{$hr->id}")->assertStatus(404);

        $this->assertDatabaseHas('heart_rates', ['id' => $hr->id]);
    }

    public function test_user_a_cannot_delete_user_b_blood_pressure(): void
    {
        $bp = BloodPressure::create([
            'user_id'     => $this->bob->id,
            'systolic'    => 120,
            'diastolic'   => 80,
            'recorded_at' => now(),
        ]);

        $this->loginAlice();
        $this->deleteJson("/blood-pressures/{$bp->id}")->assertStatus(404);

        $this->assertDatabaseHas('blood_pressures', ['id' => $bp->id]);
    }

    public function test_user_a_cannot_delete_user_b_weight(): void
    {
        $w = Weight::create([
            'user_id'     => $this->bob->id,
            'weight'      => 75.5,
            'recorded_at' => now(),
        ]);

        $this->loginAlice();
        $this->deleteJson("/weights/{$w->id}")->assertStatus(404);

        $this->assertDatabaseHas('weights', ['id' => $w->id]);
    }

    public function test_user_a_cannot_delete_user_b_steps(): void
    {
        $s = Step::create([
            'user_id'     => $this->bob->id,
            'steps'       => 8500,
            'recorded_at' => today(),
        ]);

        $this->loginAlice();
        $this->deleteJson("/steps/{$s->id}")->assertStatus(404);

        $this->assertDatabaseHas('steps', ['id' => $s->id]);
    }

    public function test_user_a_cannot_delete_user_b_calorie(): void
    {
        $c = CalorieIntake::create([
            'user_id'     => $this->bob->id,
            'data'        => 2100,
            'recorded_at' => now(),
        ]);

        $this->loginAlice();
        $this->deleteJson("/calories/{$c->id}")->assertStatus(404);

        $this->assertDatabaseHas('calorie_intakes', ['id' => $c->id]);
    }

    public function test_user_a_cannot_delete_user_b_water(): void
    {
        $w = WaterIntake::create([
            'user_id'     => $this->bob->id,
            'amount_ml'   => 500,
            'recorded_at' => now(),
        ]);

        $this->loginAlice();
        $this->deleteJson("/waters/{$w->id}")->assertStatus(404);

        $this->assertDatabaseHas('water_intakes', ['id' => $w->id]);
    }

    public function test_user_a_cannot_delete_user_b_sleep(): void
    {
        $s = SleepRecord::create([
            'user_id'     => $this->bob->id,
            'hours'       => 7.5,
            'quality'     => 4,
            'recorded_at' => now(),
        ]);

        $this->loginAlice();
        $this->deleteJson("/sleeps/{$s->id}")->assertStatus(404);

        $this->assertDatabaseHas('sleep_records', ['id' => $s->id]);
    }

    public function test_user_a_cannot_delete_user_b_exercise(): void
    {
        $activity = Activity::create([
            'type'    => 'running',
            'name_en' => 'Running',
            'name_hu' => 'Futás',
            'name_de' => 'Laufen',
        ]);

        $ex = Activity_User::create([
            'user_id'     => $this->bob->id,
            'activity_id' => $activity->id,
            'begin'       => now()->subHour(),
            'end'         => now(),
        ]);

        $this->loginAlice();
        $this->deleteJson("/exercises/{$ex->id}")->assertStatus(404);

        $this->assertDatabaseHas('activity__users', ['id' => $ex->id]);
    }
}

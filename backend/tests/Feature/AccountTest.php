<?php

namespace Tests\Feature;

use App\Models\HeartRate;
use App\Models\SleepRecord;
use App\Models\Streak;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * GDPR / Account törlés flow.
 * @see app/Http/Controllers/AuthController.php @ deleteAccount
 */
class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_account_removes_user_and_returns_200(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->deleteJson('/account');

        $response->assertStatus(200)->assertJsonPath('message', 'Account deleted successfully.');
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    /**
     * @see CLAUDE.md session 19, 13. rész — sleepRecords cascade fix
     * deleteAccount korábban kihagyta a `sleepRecords()->delete()`-t. A `sleep_records.user_id`
     * FK RESTRICT (nem cascade), így egy alvás-rekorddal rendelkező user törlése FK
     * constraint hibával szakadna fel a `$user->delete()` során.
     */
    public function test_delete_account_with_sleep_records_does_not_throw_fk_error(): void
    {
        $user = User::factory()->create();
        SleepRecord::create([
            'user_id'     => $user->id,
            'hours'       => 7.5,
            'quality'     => 4,
            'recorded_at' => now(),
        ]);

        Sanctum::actingAs($user, ['*']);
        $this->deleteJson('/account')->assertStatus(200);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('sleep_records', ['user_id' => $user->id]);
    }

    public function test_delete_account_removes_all_health_data(): void
    {
        $user = User::factory()->create();
        HeartRate::create([
            'user_id'     => $user->id,
            'heart_rate'  => 75,
            'recorded_at' => now(),
        ]);
        Streak::create([
            'user_id'  => $user->id,
            'last_day' => now()->toDateString(),
            'days'     => 5,
        ]);

        Sanctum::actingAs($user, ['*']);
        $this->deleteJson('/account')->assertStatus(200);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('heart_rates', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('streaks', ['user_id' => $user->id]);
    }

    /**
     * Másik user adatait NEM szabad törölnie az account törlésnek.
     */
    public function test_delete_account_does_not_affect_other_users_data(): void
    {
        $alice = User::factory()->create();
        $bob   = User::factory()->create();
        $bobsHr = HeartRate::create([
            'user_id'     => $bob->id,
            'heart_rate'  => 75,
            'recorded_at' => now(),
        ]);

        Sanctum::actingAs($alice, ['*']);
        $this->deleteJson('/account')->assertStatus(200);

        $this->assertDatabaseHas('users', ['id' => $bob->id]);
        $this->assertDatabaseHas('heart_rates', ['id' => $bobsHr->id]);
    }
}

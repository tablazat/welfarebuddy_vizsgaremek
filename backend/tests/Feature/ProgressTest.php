<?php

namespace Tests\Feature;

use App\Models\HeartRate;
use App\Models\Streak;
use App\Models\User;
use App\Models\Weight;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;


class ProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_progress_returns_zero_state_for_new_user(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/me/progress');

        $response->assertStatus(200)
            ->assertJsonPath('weight_start', null)
            ->assertJsonPath('weight_current', null)
            ->assertJsonPath('weight_delta', null)
            ->assertJsonPath('streak_current', 0)
            ->assertJsonPath('entries_total', 0);
    }

    public function test_progress_computes_weight_delta_from_first_to_last(): void
    {
        $user = User::factory()->create();
        Weight::create(['user_id' => $user->id, 'weight' => 80.0, 'recorded_at' => now()->subMonths(3)]);
        Weight::create(['user_id' => $user->id, 'weight' => 75.5, 'recorded_at' => now()]);
        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/me/progress');

        $response->assertStatus(200);
        $this->assertEquals(80.0, $response->json('weight_start'));
        $this->assertEquals(75.5, $response->json('weight_current'));
        $this->assertEquals(-4.5, $response->json('weight_delta'));
    }

    public function test_progress_computes_bmi_when_height_set(): void
    {
        $user = User::factory()->create(['height_cm' => 170]);
        Weight::create(['user_id' => $user->id, 'weight' => 70.0, 'recorded_at' => now()->subMonth()]);
        Weight::create(['user_id' => $user->id, 'weight' => 65.0, 'recorded_at' => now()]);
        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/me/progress');

        // 1.7² = 2.89; 70 / 2.89 = 24.2; 65 / 2.89 = 22.5
        $response->assertStatus(200);
        $this->assertEquals(24.2, $response->json('bmi_start'));
        $this->assertEquals(22.5, $response->json('bmi_current'));
    }

    public function test_progress_returns_streak_max_record(): void
    {
        $user = User::factory()->create(['max_days' => 21]);
        Streak::create(['user_id' => $user->id, 'last_day' => now()->toDateString(), 'days' => 14]);
        HeartRate::create(['user_id' => $user->id, 'heart_rate' => 75, 'recorded_at' => now()]);
        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/me/progress');

        $response->assertStatus(200)
            ->assertJsonPath('streak_current', 14)
            ->assertJsonPath('streak_max', 21)
            ->assertJsonPath('entries_total', 1);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Streak;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;


class StreakTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_streak_status_returns_days_and_last_day(): void
    {
        $user = User::factory()->create(['level_of_access' => 'free']);
        Streak::create([
            'user_id'  => $user->id,
            'last_day' => now()->toDateString(),
            'days'     => 7,
        ]);
        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/streak/status');

        $response->assertStatus(200)
            ->assertJsonPath('streak.days', 7)
            ->assertJsonPath('is_pro', false)
            ->assertJsonPath('can_freeze', false);
    }

    public function test_pro_user_can_freeze_streak_when_one_day_missed(): void
    {
        $user = User::factory()->create(['level_of_access' => 'pro']);
        Streak::create([
            'user_id'  => $user->id,
            'last_day' => now()->subDays(2)->toDateString(),
            'days'     => 5,
        ]);
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/streak/freeze');

        $response->assertStatus(200)->assertJsonPath('code', 'frozen');

        
        $streak = $user->fresh()->streak;
        $this->assertSame(now()->subDay()->toDateString(), substr((string) $streak->last_day, 0, 10));
        $this->assertNotNull($streak->last_freeze_at);
    }

    public function test_free_user_freeze_returns_403_not_pro(): void
    {
        $user = User::factory()->create(['level_of_access' => 'free']);
        Streak::create([
            'user_id'  => $user->id,
            'last_day' => now()->subDays(2)->toDateString(),
            'days'     => 5,
        ]);
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/streak/freeze');

        $response->assertStatus(403)->assertJsonPath('code', 'not_pro');
    }


    public function test_freeze_within_cooldown_returns_422_cooldown(): void
    {
        $user = User::factory()->create(['level_of_access' => 'pro']);
        Streak::create([
            'user_id'         => $user->id,
            'last_day'        => now()->subDays(2)->toDateString(),
            'days'            => 5,
            'last_freeze_at'  => now()->subDays(10)->toDateString(), // 10 napja használt freeze
        ]);
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/streak/freeze');

        $response->assertStatus(422)
            ->assertJsonPath('code', 'cooldown')
            ->assertJsonPath('cooldown_days_left', 20); // 30 - 10
    }
}

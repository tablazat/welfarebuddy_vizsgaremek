<?php

namespace Tests\Feature;

use App\Models\Streak;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * @see app/Http/Controllers/HealthSyncController.php
 * @see TODO.md Bug #59 (steps race-summing), Bug #60 (streak race), session 19 (batchHasToday)
 */
class HealthSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Streak::create([
            'user_id'  => $this->user->id,
            'last_day' => now()->subDay()->toDateString(),
            'days'     => 1,
        ]);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_batch_with_5_hr_persists_all_records(): void
    {
        $payload = [
            'heart_rates' => array_map(fn ($i) => [
                'heart_rate'  => 70 + $i,
                'recorded_at' => now()->subMinutes($i)->format('Y-m-d H:i:s'),
            ], range(0, 4)),
        ];

        $response = $this->postJson('/health-sync', $payload);

        $response->assertStatus(201)->assertJsonPath('counts.heart_rates', 5);
        $this->assertSame(5, $this->user->heartRates()->count());
    }

    public function test_batch_with_500_records_does_not_crash(): void
    {
        $payload = [
            'heart_rates' => array_map(fn ($i) => [
                'heart_rate'  => 60 + ($i % 40),
                'recorded_at' => now()->subMinutes($i)->format('Y-m-d H:i:s'),
            ], range(0, 499)),
        ];

        $response = $this->postJson('/health-sync', $payload);

        $response->assertStatus(201)->assertJsonPath('counts.heart_rates', 500);
        $this->assertSame(500, $this->user->heartRates()->count());
    }

    public function test_batch_with_501_records_returns_422(): void
    {
        $payload = [
            'heart_rates' => array_map(fn ($i) => [
                'heart_rate'  => 70,
                'recorded_at' => now()->subMinutes($i)->format('Y-m-d H:i:s'),
            ], range(0, 500)),
        ];

        $response = $this->postJson('/health-sync', $payload);

        $response->assertStatus(422)->assertJsonValidationErrors(['heart_rates']);
        $this->assertSame(0, $this->user->heartRates()->count());
    }

    /**
     * @see CLAUDE.md session 19, 4. rész — batchHasToday() guard
     */
    public function test_streak_does_not_increment_for_only_past_entries(): void
    {
        $payload = [
            'heart_rates' => [[
                'heart_rate'  => 75,
                'recorded_at' => now()->subDays(3)->format('Y-m-d H:i:s'),
            ]],
        ];

        $response = $this->postJson('/health-sync', $payload);
        $response->assertStatus(201);

        $streak = $this->user->fresh()->streak;
        $this->assertSame(1, $streak->days, 'streak must NOT increment on historical-only batch');
        $this->assertSame(now()->subDay()->toDateString(), substr((string) $streak->last_day, 0, 10));
    }

    public function test_streak_increments_when_batch_has_today_entry(): void
    {
        $payload = [
            'heart_rates' => [[
                'heart_rate'  => 80,
                'recorded_at' => now()->format('Y-m-d H:i:s'),
            ]],
        ];

        $response = $this->postJson('/health-sync', $payload);
        $response->assertStatus(201);

        $streak = $this->user->fresh()->streak;
        $this->assertSame(2, $streak->days, 'streak must increment from 1→2 (yesterday → today)');
        $this->assertSame(now()->toDateString(), substr((string) $streak->last_day, 0, 10));
    }

    /**
     * @see TODO.md Bug #59 — DB::transaction + lockForUpdate + sum same-day steps
     */
    public function test_duplicate_steps_for_same_day_get_summed_not_duplicated(): void
    {
        $today = now()->format('Y-m-d 12:00:00');

        $payload = [
            'steps' => [
                ['steps' => 3000, 'recorded_at' => $today],
                ['steps' => 5000, 'recorded_at' => $today],
            ],
        ];

        $response = $this->postJson('/health-sync', $payload);
        $response->assertStatus(201);

        $rows = $this->user->steps()->whereDate('recorded_at', now()->toDateString())->get();
        $this->assertCount(1, $rows, 'same-day step records must collapse into one row');
        $this->assertSame(8000, (int) $rows->first()->steps, 'two batches must be summed');
    }
}

<?php

namespace Tests\Feature\Services;

use App\Models\Streak;
use App\Models\User;
use App\Services\StreakService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * @see app/Services/StreakService.php
 * @see TODO.md Bug #2-4 (null pointer), Bug #19 (backdated), Bug #47/#17 (extracted to service)
 */
class StreakServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithStreak(string $lastDay, int $days, int $maxDays = 0): User
    {
        $user = User::factory()->create(['max_days' => $maxDays]);
        Streak::create([
            'user_id'  => $user->id,
            'last_day' => $lastDay,
            'days'     => $days,
        ]);
        return $user->fresh('streak');
    }

    public function test_no_op_when_user_has_no_streak(): void
    {
        $user = User::factory()->create();
        $this->assertNull($user->streak);

        // Nem dob hibát, nem hoz létre streak rekordot
        StreakService::update($user, now());

        $this->assertNull($user->fresh('streak')->streak);
    }

    /**
     * @see TODO.md Bug #19 — backdated entry nem rontja el a streaket
     */
    public function test_backdated_entry_does_not_modify_streak(): void
    {
        $user = $this->makeUserWithStreak('2026-04-20', 5);

        // Régebbi nap → semmi változás
        StreakService::update($user, '2026-04-15 10:00:00');

        $streak = $user->fresh()->streak;
        $this->assertSame(5, $streak->days);
        $this->assertSame('2026-04-20', substr((string) $streak->last_day, 0, 10));
    }

    public function test_same_day_entry_does_not_modify_streak(): void
    {
        $user = $this->makeUserWithStreak('2026-04-20', 5);

        StreakService::update($user, '2026-04-20 22:00:00');

        $streak = $user->fresh()->streak;
        $this->assertSame(5, $streak->days);
        $this->assertSame('2026-04-20', substr((string) $streak->last_day, 0, 10));
    }

    public function test_consecutive_day_increments_streak(): void
    {
        $user = $this->makeUserWithStreak('2026-04-20', 5);

        StreakService::update($user, '2026-04-21 08:30:00');

        $streak = $user->fresh()->streak;
        $this->assertSame(6, $streak->days);
        $this->assertSame('2026-04-21', substr((string) $streak->last_day, 0, 10));
    }

    public function test_gap_resets_streak_to_one(): void
    {
        $user = $this->makeUserWithStreak('2026-04-20', 5);

        // 4 nap kihagyás → reset 1-re
        StreakService::update($user, '2026-04-25 12:00:00');

        $streak = $user->fresh()->streak;
        $this->assertSame(1, $streak->days);
        $this->assertSame('2026-04-25', substr((string) $streak->last_day, 0, 10));
    }

    public function test_consecutive_increment_updates_max_days_when_exceeded(): void
    {
        $user = $this->makeUserWithStreak('2026-04-20', 9, maxDays: 9);

        StreakService::update($user, '2026-04-21 08:00:00');

        $user->refresh();
        $this->assertSame(10, $user->streak->days);
        $this->assertSame(10, $user->max_days, 'max_days must be raised when streak exceeds previous max');
    }

    public function test_consecutive_increment_keeps_max_days_when_below(): void
    {
        $user = $this->makeUserWithStreak('2026-04-20', 5, maxDays: 50);

        StreakService::update($user, '2026-04-21 08:00:00');

        $user->refresh();
        $this->assertSame(6, $user->streak->days);
        $this->assertSame(50, $user->max_days, 'max_days must NOT shrink');
    }
}

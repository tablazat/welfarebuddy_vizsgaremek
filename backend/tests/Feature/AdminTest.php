<?php

namespace Tests\Feature;

use App\Models\HeartRate;
use App\Models\Streak;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * @see app/Http/Controllers/AdminController.php
 * @see app/Http/Middleware/IsAdmin.php
 */
class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_users(): void
    {
        $admin = User::factory()->create(['level_of_access' => 'admin']);
        User::factory()->count(3)->create();
        Sanctum::actingAs($admin, ['*']);

        $response = $this->getJson('/admin/users');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'current_page', 'total']);
        // 1 admin + 3 free = 4
        $this->assertSame(4, $response->json('total'));
    }

    public function test_admin_can_view_user_details(): void
    {
        $admin  = User::factory()->create(['level_of_access' => 'admin']);
        $target = User::factory()->create();
        Streak::create(['user_id' => $target->id, 'last_day' => now()->toDateString(), 'days' => 5]);
        HeartRate::create(['user_id' => $target->id, 'heart_rate' => 75, 'recorded_at' => now()]);
        Sanctum::actingAs($admin, ['*']);

        $response = $this->getJson("/admin/users/{$target->id}");

        $response->assertStatus(200)
            ->assertJsonPath('user.id', $target->id)
            ->assertJsonPath('user.streak.days', 5)
            ->assertJsonPath('user.heart_rates_count', 1);
    }

    public function test_admin_can_change_user_level_to_pro(): void
    {
        $admin  = User::factory()->create(['level_of_access' => 'admin']);
        $target = User::factory()->create(['level_of_access' => 'free']);
        Sanctum::actingAs($admin, ['*']);

        $response = $this->putJson("/admin/users/{$target->id}/level", [
            'level_of_access' => 'pro',
        ]);

        $response->assertStatus(200)->assertJsonPath('user.level_of_access', 'pro');
        $this->assertSame('pro', $target->fresh()->level_of_access);
    }

    public function test_non_admin_admin_endpoint_returns_403(): void
    {
        $user = User::factory()->create(['level_of_access' => 'free']);
        Sanctum::actingAs($user, ['*']);

        $this->getJson('/admin/users')->assertStatus(403);
        $this->getJson('/admin/stats')->assertStatus(403);
    }

    public function test_admin_cannot_delete_themselves(): void
    {
        $admin = User::factory()->create(['level_of_access' => 'admin']);
        Sanctum::actingAs($admin, ['*']);

        $response = $this->deleteJson("/admin/users/{$admin->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }
}

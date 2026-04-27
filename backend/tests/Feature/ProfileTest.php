<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_post_height_updates_height_cm(): void
    {
        $response = $this->postJson('/height', ['height_cm' => 175]);

        $response->assertStatus(200)->assertJsonPath('user.height_cm', 175);
        $this->assertSame(175, (int) $this->user->fresh()->height_cm);
    }

    public function test_post_height_rejects_out_of_range(): void
    {
        $this->postJson('/height', ['height_cm' => 30])->assertStatus(422);
        $this->postJson('/height', ['height_cm' => 400])->assertStatus(422);

        $this->assertNull($this->user->fresh()->height_cm);
    }

    
    public function test_put_me_goals_updates_step_and_water_goal(): void
    {
        $response = $this->putJson('/me/goals', [
            'step_goal_daily' => 12000,
            'water_goal_ml'   => 3000,
        ]);

        $response->assertStatus(200);
        $user = $this->user->fresh();
        $this->assertSame(12000, (int) $user->step_goal_daily);
        $this->assertSame(3000, (int) $user->water_goal_ml);
    }

    public function test_put_me_goals_partial_update_keeps_other_field(): void
    {
        $this->user->update(['step_goal_daily' => 8000, 'water_goal_ml' => 2000]);

        $this->putJson('/me/goals', ['step_goal_daily' => 15000])->assertStatus(200);

        $user = $this->user->fresh();
        $this->assertSame(15000, (int) $user->step_goal_daily);
        $this->assertSame(2000, (int) $user->water_goal_ml, 'water_goal_ml must remain unchanged');
    }

    public function test_post_locale_changes_user_locale(): void
    {
        $response = $this->postJson('/locale', ['locale' => 'de']);

        $response->assertStatus(200);
        $this->assertSame('de', $this->user->fresh()->locale);
    }

    public function test_post_locale_rejects_unknown_code(): void
    {
        $this->postJson('/locale', ['locale' => 'fr'])->assertStatus(422);
    }

    public function test_profile_picture_upload_stores_file_and_returns_url(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('avatar.png', 200, 200);

        $response = $this->postJson('/profile-picture', ['photo' => $file]);

        $response->assertStatus(200);
        $path = $this->user->fresh()->profile_picture;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    
    public function test_profile_picture_delete_removes_file_from_storage(): void
    {
        Storage::fake('public');

        
        $file = UploadedFile::fake()->image('avatar.png');
        $this->postJson('/profile-picture', ['photo' => $file])->assertStatus(200);

        $path = $this->user->fresh()->profile_picture;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);

        
        $this->deleteJson('/profile-picture')->assertStatus(200);

        $this->assertNull($this->user->fresh()->profile_picture);
        Storage::disk('public')->assertMissing($path);
    }
}

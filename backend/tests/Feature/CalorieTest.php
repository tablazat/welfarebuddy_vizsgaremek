<?php

namespace Tests\Feature;

use App\Models\CalorieIntake;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;


class CalorieTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_new_calorie_creates_record_in_calorie_intakes_table(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/new-calorie', ['data' => 500]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('calorie_intakes', [
            'user_id' => $user->id,
            'data'    => 500,
        ]);
        
        $this->assertSame(0, $user->heartRates()->count());
        $this->assertSame(0, $user->weights()->count());
        $this->assertSame(0, $user->waterIntakes()->count());
    }

    public function test_get_calories_returns_only_own_records(): void
    {
        $alice = User::factory()->create();
        $bob   = User::factory()->create();
        CalorieIntake::create(['user_id' => $alice->id, 'data' => 1500, 'recorded_at' => now()]);
        CalorieIntake::create(['user_id' => $bob->id,   'data' => 2200, 'recorded_at' => now()]);
        Sanctum::actingAs($alice, ['*']);

        $response = $this->getJson('/calories');

        $response->assertStatus(200);
        $body = $response->json();
        $this->assertCount(1, $body);
        $this->assertSame(1500, (int) $body[0]['data']);
        $this->assertSame($alice->id, $body[0]['user_id']);
    }

    public function test_post_new_calorie_with_recorded_at_preserves_value(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $iso = '2026-04-25T20:30:00';
        $response = $this->postJson('/new-calorie', [
            'data'        => 700,
            'recorded_at' => $iso,
        ]);

        $response->assertStatus(201);
        $rec = CalorieIntake::where('user_id', $user->id)->first();
        $this->assertSame(700, (int) $rec->data);
        $this->assertStringContainsString('2026-04-25', (string) $rec->recorded_at);
    }

    public function test_post_new_calorie_validation_rejects_zero_or_missing_data(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $this->postJson('/new-calorie', ['data' => 0])->assertStatus(422);
        $this->postJson('/new-calorie', [])->assertStatus(422);
        $this->postJson('/new-calorie', ['data' => -100])->assertStatus(422);
    }

    public function test_calorie_update_only_works_for_owner(): void
    {
        $alice = User::factory()->create();
        $bob   = User::factory()->create();
        $bobsCal = CalorieIntake::create(['user_id' => $bob->id, 'data' => 1000, 'recorded_at' => now()]);
        Sanctum::actingAs($alice, ['*']);

        $response = $this->putJson("/calories/{$bobsCal->id}", ['data' => 9999]);

        $response->assertStatus(404);
        $this->assertSame(1000, (int) $bobsCal->fresh()->data);
    }
}

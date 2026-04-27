<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    
    protected function actingAsUser(array $overrides = []): User
    {
        $user = User::factory()->create($overrides);
        Sanctum::actingAs($user, ['*']);
        return $user;
    }

    protected function actingAsAdmin(array $overrides = []): User
    {
        return $this->actingAsUser(array_merge(['level_of_access' => 'admin'], $overrides));
    }

    protected function actingAsPro(array $overrides = []): User
    {
        return $this->actingAsUser(array_merge(['level_of_access' => 'pro'], $overrides));
    }
}

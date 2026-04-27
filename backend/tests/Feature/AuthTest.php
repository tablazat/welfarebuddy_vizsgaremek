<?php

namespace Tests\Feature;

use App\Models\Streak;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_user_streak_and_token(): void
    {
        $payload = [
            'name'                  => 'Test User',
            'email'                 => 'test@example.com',
            'password'              => 'password123',
            'terms_accepted'        => true,
        ];

        $response = $this->postJson('/register', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email'], 'streak' => ['id', 'days', 'last_day']]);

        $this->assertDatabaseHas('users', ['email' => 'test@example.com', 'name' => 'Test User']);
        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user->terms_accepted_at, 'terms_accepted_at audit timestamp must be set');
        $this->assertDatabaseHas('streaks', ['user_id' => $user->id]);
    }

    public function test_register_rejects_existing_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->postJson('/register', [
            'name'           => 'Second User',
            'email'          => 'taken@example.com',
            'password'       => 'password123',
            'terms_accepted' => true,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    
    public function test_register_rejects_short_password(): void
    {
        $response = $this->postJson('/register', [
            'name'           => 'Test User',
            'email'          => 'test@example.com',
            'password'       => 'short',
            'terms_accepted' => true,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    
    public function test_register_rejects_without_terms_accepted(): void
    {
        $response = $this->postJson('/register', [
            'name'     => 'Test User',
            'email'    => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['terms_accepted']);

        $response2 = $this->postJson('/register', [
            'name'           => 'Test User',
            'email'          => 'test2@example.com',
            'password'       => 'password123',
            'terms_accepted' => false,
        ]);
        $response2->assertStatus(422)->assertJsonValidationErrors(['terms_accepted']);
    }

    public function test_login_returns_token_for_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email'    => 'login@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/login', [
            'email'    => 'login@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['token', 'user' => ['id', 'email']])
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_login_rejects_wrong_password(): void
    {
        User::factory()->create([
            'email'    => 'login@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/login', [
            'email'    => 'login@example.com',
            'password' => 'wrongpass',
        ]);

        $response->assertStatus(401);
    }

    
    public function test_login_unverified_user_returns_token_but_email_not_verified(): void
    {
        $user = User::factory()->unverified()->create([
            'email'    => 'pending@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/login', [
            'email'    => 'pending@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('user.email_verified_at', null);
    }

    public function test_password_reset_flow_updates_hash(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email'    => 'reset@example.com',
            'password' => Hash::make('originalpass'),
        ]);

        $forgot = $this->postJson('/forgot-password', ['email' => 'reset@example.com']);
        $forgot->assertStatus(200);

        
        $token = Password::createToken($user);

        $reset = $this->postJson('/reset-password', [
            'token'                 => $token,
            'email'                 => 'reset@example.com',
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);
        $reset->assertStatus(200);

        $user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $user->password), 'New password hash must verify');
        $this->assertFalse(Hash::check('originalpass', $user->password), 'Old password must no longer verify');
    }

    
    public function test_email_verification_marks_user_verified(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::signedRoute('verification.verify', [
            'id'   => $user->id,
            'hash' => sha1($user->email),
        ]);

        $response = $this->getJson($url);
        $response->assertStatus(200)->assertJsonPath('code', 'verified');

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_email_verification_rejects_invalid_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::signedRoute('verification.verify', [
            'id'   => $user->id,
            'hash' => 'wrong-hash',
        ]);

        $response = $this->getJson($url);
        $response->assertStatus(403)->assertJsonPath('code', 'invalid_link');

        $user->refresh();
        $this->assertNull($user->email_verified_at);
    }

    public function test_login_failed_message_is_localized_per_accept_language(): void
    {
        User::factory()->create([
            'email'    => 'loc@example.com',
            'password' => Hash::make('password123'),
        ]);

        $cases = [
            'hu' => 'Helytelen e-mail cím vagy jelszó.',
            'en' => 'These credentials do not match our records.',
            'de' => 'Ungültige E-Mail-Adresse oder Passwort.',
        ];

        foreach ($cases as $locale => $expected) {
            $response = $this->withHeaders(['Accept-Language' => $locale])
                ->postJson('/login', [
                    'email'    => 'loc@example.com',
                    'password' => 'wrongpass',
                ]);

            $response->assertStatus(401)
                ->assertJsonPath('message', $expected);
        }
    }

    public function test_validation_errors_are_localized_per_accept_language(): void
    {
        $cases = [
            'hu' => 'jelszó',
            'de' => 'Passwort',
            'en' => 'password',
        ];

        foreach ($cases as $locale => $expectedAttr) {
            $response = $this->withHeaders(['Accept-Language' => $locale])
                ->postJson('/register', [
                    'name'           => 'Test',
                    'email'          => "loc-{$locale}@example.com",
                    'password'       => 'short',
                    'terms_accepted' => true,
                ]);

            $response->assertStatus(422);
            $body = $response->json();
            $this->assertNotEmpty($body['errors']['password'] ?? null);
            $this->assertStringContainsString(
                $expectedAttr,
                $body['errors']['password'][0],
                "{$locale} locale: 'password' attribute fordítása az üzenetben"
            );
        }
    }

    public function test_authenticated_user_locale_overrides_accept_language(): void
    {
        $user = User::factory()->create(['locale' => 'hu']);

        \Laravel\Sanctum\Sanctum::actingAs($user, ['*']);
        $response = $this->withHeaders(['Accept-Language' => 'de'])
            ->putJson('/me/profile', [
                'display_name' => str_repeat('a', 100),
            ]);

        $response->assertStatus(422);
        $body = $response->json();
        $this->assertStringContainsString(
            'becenév',
            $body['errors']['display_name'][0] ?? '',
            'user.locale=hu felülírja az Accept-Language=de-t'
        );
    }
}

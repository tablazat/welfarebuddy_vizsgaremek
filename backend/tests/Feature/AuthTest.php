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

    /**
     * @see TODO.md Bug #63 — login és register `min:8` szinkron
     */
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

    /**
     * @see CLAUDE.md session 19, 10. rész — GDPR Art. 6(1)(a) kifejezett hozzájárulás
     */
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

    /**
     * @see TODO.md Bug #38 — verify e-mail flow, login engedi a tokent unverified usernek is
     */
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

        // A Password broker programmatic token-t generál — a notification mailen menne, de fake.
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

    /**
     * @see TODO.md Bug #20, #62 — verify response code mező (verified/already_verified/invalid_link)
     */
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
}

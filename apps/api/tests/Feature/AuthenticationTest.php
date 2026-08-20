<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private const VALID_PASSWORD = 'Correct-Horse-7!';

    public function test_registration_normalises_email_and_sends_a_signed_laravel_verification_link(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/auth/register', [
            'name' => '  David Oliver  ',
            'email' => '  DAVID@Example.COM ',
            'password' => self::VALID_PASSWORD,
            'password_confirmation' => self::VALID_PASSWORD,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.user.email', 'david@example.com');

        $user = User::where('email', 'david@example.com')->firstOrFail();

        $this->assertSame('David Oliver', $user->name);
        $this->assertNull($user->email_verified_at);

        Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $notification) use ($user) {
            $url = $notification->toMail($user)->actionUrl;

            return str_starts_with(
                $url,
                rtrim(config('app.url'), '/').'/api/auth/email/verify/'
            )
                && str_contains($url, 'signature=');
        });
    }

    public function test_registration_can_be_disabled_without_changing_the_contract(): void
    {
        config()->set('fortify.registration_enabled', false);

        $this->postJson('/api/auth/register', [
            'name' => 'Invite Only',
            'email' => 'invite@example.com',
            'password' => self::VALID_PASSWORD,
            'password_confirmation' => self::VALID_PASSWORD,
        ])->assertNotFound();

        $this->assertDatabaseMissing('users', ['email' => 'invite@example.com']);
    }

    public function test_canonical_email_is_unique(): void
    {
        User::factory()->create(['email' => 'david@example.com']);

        $this->postJson('/api/auth/register', [
            'name' => 'Duplicate',
            'email' => ' DAVID@EXAMPLE.COM ',
            'password' => self::VALID_PASSWORD,
            'password_confirmation' => self::VALID_PASSWORD,
        ])->assertJsonValidationErrors('email');

        $this->assertSame(1, User::where('email', 'david@example.com')->count());
    }

    public function test_initial_password_policy_is_enforced(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Weak Password',
            'email' => 'weak@example.com',
            'password' => 'alllowercase12',
            'password_confirmation' => 'alllowercase12',
        ])->assertJsonValidationErrors('password');
    }

    public function test_login_uses_canonical_email_and_returns_the_user(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $user = User::factory()->unverified()->create([
            'email' => 'david@example.com',
            'password' => Hash::make(self::VALID_PASSWORD),
        ]);

        $response = $this
            ->withHeader('Origin', 'http://localhost:3000')
            ->postJson('/api/auth/login', [
                'email' => ' DAVID@EXAMPLE.COM ',
                'password' => self::VALID_PASSWORD,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id);

        $sessionCookie = collect($response->headers->getCookies())
            ->firstWhere(fn ($cookie) => $cookie->getName() === config('session.cookie'));

        $this->assertNotNull($sessionCookie);
        $this->assertTrue($sessionCookie->isHttpOnly());
        $this->assertAuthenticatedAs($user);
    }

    public function test_authenticated_login_attempt_fails_with_json_instead_of_redirecting(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => self::VALID_PASSWORD,
            ])
            ->assertConflict()
            ->assertJsonPath('message', 'You are already signed in.')
            ->assertHeaderMissing('Location');
    }

    public function test_authenticated_registration_attempt_fails_with_json_without_creating_a_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/auth/register', [
                'name' => 'Another Account',
                'email' => 'another@example.test',
                'password' => self::VALID_PASSWORD,
                'password_confirmation' => self::VALID_PASSWORD,
            ])
            ->assertConflict()
            ->assertJsonPath('message', 'You are already signed in.')
            ->assertHeaderMissing('Location');

        $this->assertDatabaseMissing('users', ['email' => 'another@example.test']);
    }

    public function test_login_failure_does_not_reveal_account_existence(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'missing@example.com',
            'password' => self::VALID_PASSWORD,
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertStringNotContainsString('missing@example.com', $response->getContent());
    }

    public function test_unverified_user_can_read_identity_and_logout_but_not_use_platform(): void
    {
        $user = User::factory()->unverified()->create([
            'password' => Hash::make(self::VALID_PASSWORD),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => self::VALID_PASSWORD,
        ])->assertOk();

        $this->getJson('/api/auth/user')
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id);

        $this->getJson('/api/platform/status')
            ->assertForbidden();

        $this->postJson('/api/auth/logout')
            ->assertNoContent();

        // HTTP tests reuse the application container, so clear its cached guard
        // before simulating the browser's next request with the invalidated session.
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/auth/user')->assertUnauthorized();
    }

    public function test_verified_user_can_use_platform_functionality(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/platform/status')
            ->assertOk()
            ->assertJsonPath('data.status', 'available');
    }

    public function test_signed_verification_endpoint_verifies_and_redirects_to_next(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $this->actingAs($user)
            ->get($url)
            ->assertRedirect('http://localhost:3000/verify-email/result?status=verified');

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_signed_verification_link_preserves_an_authenticated_return_instead_of_rendering_a_json_401(): void
    {
        $user = User::factory()->unverified()->create([
            'password' => Hash::make('ValidPassword1!'),
        ]);
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $this->get($url)
            ->assertRedirect('http://localhost:3000/login');

        $login = $this->withHeader('Origin', 'http://localhost:3000')
            ->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'ValidPassword1!',
            ])->assertOk();

        $this->assertSame($url, $login->json('data.redirect_to'));
        $this->get($url)
            ->assertRedirect('http://localhost:3000/verify-email/result?status=verified');
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_verification_notification_can_be_resent_before_verification(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->postJson('/api/auth/email/verification-notification')
            ->assertAccepted();

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_password_reset_request_is_generic_for_known_and_unknown_accounts(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'known@example.com']);

        $known = $this->postJson('/api/auth/forgot-password', [
            'email' => ' KNOWN@EXAMPLE.COM ',
        ])->assertOk();

        $unknown = $this->postJson('/api/auth/forgot-password', [
            'email' => 'unknown@example.com',
        ])->assertOk();

        $this->assertSame($known->json(), $unknown->json());
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_password_reset_rejects_malformed_email_with_useful_validation(): void
    {
        $this->postJson('/api/auth/forgot-password', [
            'email' => 'not-an-email',
        ])->assertJsonValidationErrors('email');
    }

    public function test_password_reset_invalidates_all_database_sessions_and_rotates_remember_token(): void
    {
        $user = User::factory()->create([
            'email' => 'reset@example.com',
            'remember_token' => 'original-token',
        ]);
        $token = Password::broker()->createToken($user);

        DB::table('sessions')->insert([
            [
                'id' => 'session-one',
                'user_id' => $user->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'payload' => '',
                'last_activity' => now()->timestamp,
            ],
            [
                'id' => 'session-two',
                'user_id' => $user->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'payload' => '',
                'last_activity' => now()->timestamp,
            ],
        ]);

        $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => ' RESET@EXAMPLE.COM ',
            'password' => 'New-Correct-8!',
            'password_confirmation' => 'New-Correct-8!',
        ])->assertOk();

        $user->refresh();

        $this->assertTrue(Hash::check('New-Correct-8!', $user->password));
        $this->assertNotSame('original-token', $user->remember_token);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
    }

    public function test_xsrf_cookie_is_readable_while_session_cookie_is_http_only(): void
    {
        $response = $this->get('/sanctum/csrf-cookie');
        $xsrfCookie = collect($response->headers->getCookies())
            ->firstWhere(fn ($cookie) => $cookie->getName() === 'XSRF-TOKEN');

        $this->assertNotNull($xsrfCookie);
        $this->assertFalse($xsrfCookie->isHttpOnly());
        $this->assertTrue(config('session.http_only'));
        $this->assertSame(120, config('session.lifetime'));
    }
}

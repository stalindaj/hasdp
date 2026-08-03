<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The transport/browser hardening layer: security headers on every response,
 * the FORCE_HTTPS redirect, and the password policy.
 */
class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_response_carries_the_hardening_headers(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    public function test_plain_http_redirects_to_https_when_forced(): void
    {
        config(['app.force_https' => true]);

        $this->get('http://civdir.test/login')
            ->assertRedirect('https://civdir.test/login')
            ->assertStatus(301);
    }

    public function test_https_requests_pass_through_and_get_hsts(): void
    {
        config(['app.force_https' => true]);

        $this->get('https://civdir.test/login')
            ->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=15552000');
    }

    public function test_http_is_untouched_while_the_flag_is_off(): void
    {
        config(['app.force_https' => false]);

        $this->get('/login')->assertOk();
    }

    public function test_weak_passwords_are_rejected_on_update(): void
    {
        $user = User::factory()->create();

        // Too short / no numbers — the very mistake we're preventing.
        $this->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password'      => 'password',
                'password'              => 'password1',
                'password_confirmation' => 'password1',
            ])->assertSessionHasErrors('password');

        $this->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password'      => 'password',
                'password'              => 'strike-wing-2026',
                'password_confirmation' => 'strike-wing-2026',
            ])->assertSessionHasNoErrors();
    }
}

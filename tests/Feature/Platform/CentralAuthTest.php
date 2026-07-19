<?php

namespace Tests\Feature\Platform;

use App\Models\CentralUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The platform console runs on the `central` guard against the central
 * database. Authorization is guard membership — a row in `central_users` IS a
 * platform administrator — so a tenant user can never reach it.
 */
class CentralAuthTest extends TestCase
{
    use RefreshDatabase;

    private function centralAdmin(array $attributes = []): CentralUser
    {
        return CentralUser::query()->create(array_merge([
            'name' => 'Platform Admin',
            'email' => 'super@example.test',
            'password' => Hash::make('P@ssword123'),
            'is_active' => true,
        ], $attributes));
    }

    public function test_a_platform_admin_can_sign_in_to_the_console(): void
    {
        $this->centralAdmin();

        $response = $this->post('/platform/login', [
            'email' => 'super@example.test',
            'password' => 'P@ssword123',
        ]);

        $response->assertRedirect(route('platform.dashboard'));
        $this->assertAuthenticated('central');

        // Signing in to the console must not sign anyone in to a tenant.
        $this->assertGuest('web');
    }

    public function test_a_disabled_platform_admin_cannot_sign_in(): void
    {
        $this->centralAdmin(['is_active' => false]);

        $response = $this->post('/platform/login', [
            'email' => 'super@example.test',
            'password' => 'P@ssword123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('central');
    }

    /**
     * There is no `users` table in the central database at all, so a tenant
     * user is not merely rejected by the console — they are unreachable from
     * it. Covered end-to-end with a real tenant in
     * Tests\Feature\Tenancy\EmailDomainLoginTest.
     */
    public function test_credentials_that_are_not_a_platform_admin_are_rejected(): void
    {
        $this->centralAdmin();

        $response = $this->post('/platform/login', [
            'email' => 'admin@sometenant.test',
            'password' => 'P@ssword123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('central');
    }

    public function test_the_console_is_closed_to_guests(): void
    {
        $this->get('/platform')->assertRedirect(route('platform.login'));
    }

    /**
     * An expired console session must not bounce the administrator to the
     * tenant login, which could never resolve a tenant for their email.
     */
    public function test_an_unauthenticated_console_request_goes_to_the_platform_login(): void
    {
        $this->get('/platform/companies/create')
            ->assertRedirect(route('platform.login'));
    }

    public function test_signing_out_of_the_console_returns_to_the_platform_login(): void
    {
        $admin = $this->centralAdmin();

        $this->actingAs($admin, 'central')
            ->post('/platform/logout')
            ->assertRedirect(route('platform.login'));

        $this->assertGuest('central');
    }
}

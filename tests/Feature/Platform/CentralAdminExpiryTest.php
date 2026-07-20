<?php

namespace Tests\Feature\Platform;

use App\Models\CentralUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * A platform administrator account can carry an end date, mirroring the
 * subscription window on companies.
 *
 * Expiry is enforced at login AND on every console request, so revoking access
 * takes effect immediately rather than whenever the session happens to lapse.
 */
class CentralAdminExpiryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $attributes = []): CentralUser
    {
        static $n = 0;
        $n++;

        return CentralUser::query()->create(array_merge([
            'name' => 'Admin ' . $n,
            'email' => 'admin' . $n . '@example.test',
            'password' => Hash::make('P@ssword123'),
            'is_active' => true,
        ], $attributes));
    }

    public function test_an_admin_with_no_expiry_can_sign_in(): void
    {
        $this->admin(['email' => 'open@example.test', 'expires_on' => null]);

        $this->post('/platform/login', ['email' => 'open@example.test', 'password' => 'P@ssword123'])
            ->assertRedirect(route('platform.dashboard'));

        $this->assertAuthenticated('central');
    }

    public function test_an_admin_expiring_today_can_still_sign_in(): void
    {
        $this->admin(['email' => 'today@example.test', 'expires_on' => today()]);

        $this->post('/platform/login', ['email' => 'today@example.test', 'password' => 'P@ssword123'])
            ->assertRedirect(route('platform.dashboard'));

        $this->assertAuthenticated('central');
    }

    public function test_an_expired_admin_cannot_sign_in(): void
    {
        $this->admin(['email' => 'gone@example.test', 'expires_on' => today()->subDay()]);

        $this->post('/platform/login', ['email' => 'gone@example.test', 'password' => 'P@ssword123'])
            ->assertSessionHasErrors('email');

        $this->assertGuest('central');
    }

    /**
     * The whole point of re-checking on every request: an account that expires
     * or is disabled while its owner is signed in loses access at once.
     */
    public function test_expiring_an_admin_ends_their_open_session(): void
    {
        $me = $this->admin();
        $this->admin();

        $this->actingAs($me, 'central')->get(route('platform.dashboard'))->assertOk();

        $me->forceFill(['expires_on' => today()->subDay()])->save();

        $this->get(route('platform.dashboard'))->assertRedirect(route('platform.login'));
        $this->assertGuest('central');
    }

    public function test_disabling_an_admin_ends_their_open_session(): void
    {
        $me = $this->admin();
        $this->admin();

        $this->actingAs($me, 'central')->get(route('platform.dashboard'))->assertOk();

        $me->forceFill(['is_active' => false])->save();

        $this->get(route('platform.dashboard'))->assertRedirect(route('platform.login'));
        $this->assertGuest('central');
    }

    public function test_deleting_an_admin_ends_their_open_session(): void
    {
        $me = $this->admin();
        $this->admin();

        $this->actingAs($me, 'central')->get(route('platform.dashboard'))->assertOk();

        $me->delete();

        $this->get(route('platform.dashboard'))->assertRedirect(route('platform.login'));
        $this->assertGuest('central');
    }
}

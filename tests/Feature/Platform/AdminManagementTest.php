<?php

namespace Tests\Feature\Platform;

use App\Models\CentralUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Managing platform administrators from the console.
 *
 * There is no role model — a row in `central_users` IS the privilege — so the
 * behaviour worth pinning down is the lockout guards: the console must never be
 * left with nobody able to sign in, and an administrator must never be able to
 * revoke their own access.
 */
class AdminManagementTest extends TestCase
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

    public function test_an_admin_can_create_another_admin(): void
    {
        $this->actingAs($this->admin(), 'central')
            ->post(route('platform.admins.store'), [
                'name' => 'Sita Sharma',
                'email' => 'Sita@Example.test',
                'password' => 'P@ssword123',
                'password_confirmation' => 'P@ssword123',
                'expires_on' => today()->addMonths(6)->toDateString(),
                'is_active' => 1,
            ])->assertRedirect();

        // The email is normalised, so a later sign-in cannot miss by case.
        $created = CentralUser::query()->where('email', 'sita@example.test')->firstOrFail();

        $this->assertTrue($created->is_active);
        $this->assertTrue(Hash::check('P@ssword123', $created->password));
    }

    public function test_the_list_can_be_searched_and_filtered(): void
    {
        $this->admin(['name' => 'Sita Sharma', 'email' => 'sita@example.test']);
        $this->admin(['name' => 'Ramesh Thapa', 'email' => 'ramesh@example.test', 'is_active' => false]);

        $me = $this->admin(['name' => 'Zed Owner', 'email' => 'zed@example.test']);

        $this->actingAs($me, 'central')
            ->get(route('platform.admins.index', ['q' => 'sita']))
            ->assertOk()
            ->assertSee('Sita Sharma')
            ->assertDontSee('Ramesh Thapa');

        $this->actingAs($me, 'central')
            ->get(route('platform.admins.index', ['status' => 'disabled']))
            ->assertOk()
            ->assertSee('Ramesh Thapa')
            ->assertDontSee('Sita Sharma');
    }

    /**
     * An account can be is_active AND past its end date, so the "active" filter
     * has to exclude expired rows rather than trusting the flag.
     */
    public function test_the_active_filter_excludes_an_expired_account(): void
    {
        $this->admin(['name' => 'Anjali Gurung', 'email' => 'anjali@example.test', 'is_active' => true, 'expires_on' => today()->subDay()]);
        $me = $this->admin(['name' => 'Zed Owner', 'email' => 'zed@example.test']);

        $this->actingAs($me, 'central')
            ->get(route('platform.admins.index', ['status' => 'active']))
            ->assertOk()
            ->assertDontSee('Anjali Gurung');

        $this->actingAs($me, 'central')
            ->get(route('platform.admins.index', ['status' => 'expired']))
            ->assertOk()
            ->assertSee('Anjali Gurung');
    }

    public function test_an_admin_can_view_another_admins_detail_page(): void
    {
        $target = $this->admin(['name' => 'Sita Sharma', 'email' => 'sita@example.test']);

        $this->actingAs($this->admin(), 'central')
            ->get(route('platform.admins.show', $target))
            ->assertOk()
            ->assertSee('Sita Sharma')
            ->assertSee('sita@example.test');
    }

    public function test_an_admin_can_be_disabled_and_re_enabled(): void
    {
        $me = $this->admin();
        $target = $this->admin();

        $this->actingAs($me, 'central')
            ->patch(route('platform.admins.status', $target));

        $this->assertFalse($target->fresh()->is_active);

        $this->actingAs($me, 'central')
            ->patch(route('platform.admins.status', $target));

        $this->assertTrue($target->fresh()->is_active);
    }

    public function test_an_admin_cannot_disable_or_delete_themselves(): void
    {
        $me = $this->admin();
        $this->admin();

        $this->actingAs($me, 'central')->patch(route('platform.admins.status', $me));
        $this->assertTrue($me->fresh()->is_active);

        $this->actingAs($me, 'central')->delete(route('platform.admins.destroy', $me));
        $this->assertNotNull($me->fresh());
    }

    public function test_the_last_usable_admin_cannot_be_disabled_or_deleted(): void
    {
        $me = $this->admin();
        // Present but unusable, so it is not a way back in.
        $this->admin(['is_active' => false]);

        $this->actingAs($me, 'central')->delete(route('platform.admins.destroy', $me));
        $this->assertNotNull($me->fresh());

        // A second admin who cannot sign in must not unlock disabling either.
        $other = $this->admin(['expires_on' => today()->subDay()]);

        $this->actingAs($other, 'central')
            ->patch(route('platform.admins.status', $me));

        $this->assertTrue($me->fresh()->is_active);
    }

    public function test_an_admin_can_be_deleted_when_another_can_still_sign_in(): void
    {
        $me = $this->admin();
        $target = $this->admin();

        $this->actingAs($me, 'central')
            ->delete(route('platform.admins.destroy', $target))
            ->assertRedirect(route('platform.admins.index'));

        $this->assertNull($target->fresh());
    }

    public function test_changing_your_own_password_requires_the_current_one(): void
    {
        $me = $this->admin();

        $this->actingAs($me, 'central')
            ->put(route('platform.admins.password.update', $me), [
                'current_password' => 'WrongPassword1!',
                'password' => 'NewP@ssword123',
                'password_confirmation' => 'NewP@ssword123',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('P@ssword123', $me->fresh()->password));

        $this->actingAs($me, 'central')
            ->put(route('platform.admins.password.update', $me), [
                'current_password' => 'P@ssword123',
                'password' => 'NewP@ssword123',
                'password_confirmation' => 'NewP@ssword123',
            ])
            ->assertRedirect(route('platform.admins.show', $me));

        $this->assertTrue(Hash::check('NewP@ssword123', $me->fresh()->password));

        // Changing your own password must not sign you out.
        $this->assertAuthenticated('central');
    }

    /**
     * Resetting someone else's password does not ask for their old one — an
     * administrator locked out of their account is exactly who needs it.
     */
    public function test_another_admins_password_can_be_reset_without_the_old_one(): void
    {
        $me = $this->admin();
        $target = $this->admin();
        $oldToken = $target->remember_token;

        $this->actingAs($me, 'central')
            ->put(route('platform.admins.password.update', $target), [
                'password' => 'NewP@ssword123',
                'password_confirmation' => 'NewP@ssword123',
            ])
            ->assertRedirect(route('platform.admins.show', $target));

        $target = $target->fresh();

        $this->assertTrue(Hash::check('NewP@ssword123', $target->password));
        // The token is cycled, which signs the target out everywhere.
        $this->assertNotSame($oldToken, $target->remember_token);
    }

    public function test_editing_details_does_not_touch_the_password(): void
    {
        $me = $this->admin();
        $target = $this->admin();

        $this->actingAs($me, 'central')
            ->put(route('platform.admins.update', $target), [
                'name' => 'Renamed Person',
                'email' => 'renamed@example.test',
                'is_active' => 1,
            ])->assertRedirect();

        $target = $target->fresh();

        $this->assertSame('Renamed Person', $target->name);
        $this->assertTrue(Hash::check('P@ssword123', $target->password));
    }
}

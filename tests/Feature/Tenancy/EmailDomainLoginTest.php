<?php

namespace Tests\Feature\Tenancy;

use App\Http\Middleware\InitializeTenancyFromSession;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TenantTestCase;

/**
 * The tenant is identified by the domain part of the login email, and each
 * company's data lives in its own database.
 */
class EmailDomainLoginTest extends TenantTestCase
{
    private function user(string $email): User
    {
        $user = new User();

        $user->forceFill([
            'name' => 'Test User',
            'email' => $email,
            'password' => Hash::make('P@ssword123'),
            'account_status' => 'active',
            'approved_at' => now(),
        ])->save();

        return $user;
    }

    public function test_login_resolves_the_company_from_the_email_domain(): void
    {
        $this->user('niraj@testco.test');

        $response = $this->post('/login', [
            'email' => 'niraj@testco.test',
            'password' => 'P@ssword123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $this->assertSame(
            $this->tenant->getTenantKey(),
            session(InitializeTenancyFromSession::SESSION_KEY)
        );
    }

    public function test_an_unregistered_email_domain_is_rejected(): void
    {
        $response = $this->post('/login', [
            'email' => 'someone@gmail.com',
            'password' => 'P@ssword123',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'No company is registered for this email domain.',
        ]);
        $this->assertGuest();
    }

    public function test_a_suspended_company_cannot_sign_in(): void
    {
        $this->user('niraj@testco.test');
        $this->tenant->update(['status' => 'suspended']);

        $response = $this->post('/login', [
            'email' => 'niraj@testco.test',
            'password' => 'P@ssword123',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'This company account is suspended. Please contact support.',
        ]);
        $this->assertGuest();
    }

    public function test_an_expired_company_cannot_sign_in(): void
    {
        $this->user('niraj@testco.test');
        $this->tenant->update(['expires_on' => now()->subDay()->toDateString()]);

        $response = $this->post('/login', [
            'email' => 'niraj@testco.test',
            'password' => 'P@ssword123',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'This company account has expired. Please contact support.',
        ]);
        $this->assertGuest();
    }

    /**
     * Suspending a company must take effect for users who are already signed
     * in, not just at the next login.
     */
    public function test_suspending_a_company_ends_an_open_session(): void
    {
        $this->user('niraj@testco.test');

        $this->post('/login', [
            'email' => 'niraj@testco.test',
            'password' => 'P@ssword123',
        ])->assertRedirect(route('dashboard'));

        $this->tenant->update(['status' => 'suspended']);

        $this->get('/dashboard')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'email' => 'This company account is suspended. Please contact support.',
            ]);

        // The tenant id is cleared, so the session cannot be reused.
        $this->assertNull(session(InitializeTenancyFromSession::SESSION_KEY));
    }

    public function test_a_provisioning_company_cannot_be_signed_in_to(): void
    {
        $this->user('niraj@testco.test');
        $this->tenant->update(['status' => 'provisioning']);

        $this->post('/login', [
            'email' => 'niraj@testco.test',
            'password' => 'P@ssword123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /**
     * A real, valid tenant user cannot reach the platform console: they live in
     * their company's database, and the console authenticates against
     * `central_users`, which they are not in.
     */
    public function test_a_tenant_user_cannot_sign_in_to_the_platform_console(): void
    {
        $this->user('admin@testco.test');

        $this->post('/platform/login', [
            'email' => 'admin@testco.test',
            'password' => 'P@ssword123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('central');
    }

    public function test_a_company_gets_its_own_database_with_the_full_schema(): void
    {
        $database = $this->tenant->getAttribute('tenancy_db_name');

        $this->assertSame(config('tenancy.database.prefix') . 'testco', $database);

        $tables = $this->tenant->run(
            fn () => count(\Illuminate\Support\Facades\DB::select('SHOW TABLES'))
        );

        // The full tenant schema, not a stub.
        $this->assertGreaterThan(60, $tables);

        // Seeded by TenantDatabaseSeeder during provisioning.
        $this->assertGreaterThan(0, $this->tenant->run(fn () => \App\Models\Role::query()->count()));
        $this->assertGreaterThan(0, $this->tenant->run(fn () => \App\Models\Permission::query()->count()));
    }

    /**
     * A tenant-side `super-admin` role would let a company admin escalate to
     * the platform console, so it must not exist in the tenant catalogue.
     */
    public function test_no_super_admin_role_exists_inside_a_tenant(): void
    {
        $this->assertFalse(
            \App\Models\Role::query()->where('slug', 'super-admin')->exists()
        );
    }

    public function test_the_central_database_holds_no_tenant_tables(): void
    {
        $central = collect(\Illuminate\Support\Facades\DB::connection('mysql')->select('SHOW TABLES'))
            ->map(fn ($row) => array_values((array) $row)[0])
            ->all();

        $this->assertContains('companies', $central);
        $this->assertContains('central_users', $central);
        $this->assertContains('sessions', $central);

        $this->assertNotContains('users', $central);
        $this->assertNotContains('employees', $central);
    }
}

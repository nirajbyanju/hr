<?php

namespace Tests\Feature\Tenancy;

use App\Models\Company;
use App\Models\User;
use App\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The tenant is identified by the domain part of the login email:
 * nirajbyanju@ktm.com resolves to the company whose domain is "ktm.com".
 */
class EmailDomainLoginTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Exercises the real seeder path so this test fails if the default company
     * ever stops getting a domain.
     */
    private function seedDefaultCompany(): Company
    {
        $seeder = new class
        {
            use \Database\Seeders\Concerns\ResolvesDefaultCompany;

            public function make(): Company
            {
                return $this->defaultCompany();
            }
        };

        return $seeder->make();
    }

    private function company(string $name, string $domain, array $attributes = []): Company
    {
        return Company::query()->create(array_merge([
            'name' => $name,
            'slug' => str($name)->slug()->value(),
            'domain' => $domain,
            'status' => 'active',
        ], $attributes));
    }

    private function user(Company $company, string $email): User
    {
        $user = new User();

        $user->forceFill([
            'company_id' => $company->id,
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
        $ktm = $this->company('KTM Group', 'ktm.com');
        $this->user($ktm, 'nirajbyanju@ktm.com');

        $response = $this->post('/login', [
            'email' => 'nirajbyanju@ktm.com',
            'password' => 'P@ssword123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $this->assertSame($ktm->id, auth()->user()->company_id);
    }

    public function test_the_resolved_tenant_matches_the_signed_in_users_company(): void
    {
        $this->company('Acme Ltd', 'acme.com');
        $ktm = $this->company('KTM Group', 'ktm.com');
        $this->user($ktm, 'nirajbyanju@ktm.com');

        $this->post('/login', [
            'email' => 'nirajbyanju@ktm.com',
            'password' => 'P@ssword123',
        ]);

        // A follow-up request must re-establish KTM as the active tenant from
        // the signed-in user, whatever the response status turns out to be.
        $this->get('/dashboard');

        $this->assertSame($ktm->id, app(Tenancy::class)->id());
    }

    public function test_a_user_cannot_sign_in_through_another_companys_domain(): void
    {
        $acme = $this->company('Acme Ltd', 'acme.com');
        $this->company('KTM Group', 'ktm.com');

        // The account lives in Acme but the address claims the KTM domain.
        $this->user($acme, 'impostor@ktm.com');

        $response = $this->post('/login', [
            'email' => 'impostor@ktm.com',
            'password' => 'P@ssword123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_an_unregistered_email_domain_is_rejected(): void
    {
        $this->company('KTM Group', 'ktm.com');

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
        $ktm = $this->company('KTM Group', 'ktm.com', ['status' => 'suspended']);
        $this->user($ktm, 'nirajbyanju@ktm.com');

        $response = $this->post('/login', [
            'email' => 'nirajbyanju@ktm.com',
            'password' => 'P@ssword123',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'This company account is suspended. Please contact support.',
        ]);
        $this->assertGuest();
    }

    public function test_an_expired_company_cannot_sign_in(): void
    {
        $ktm = $this->company('KTM Group', 'ktm.com', [
            'expires_on' => now()->subDay()->toDateString(),
        ]);
        $this->user($ktm, 'nirajbyanju@ktm.com');

        $response = $this->post('/login', [
            'email' => 'nirajbyanju@ktm.com',
            'password' => 'P@ssword123',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'This company account has expired. Please contact support.',
        ]);
        $this->assertGuest();
    }

    // Platform console auth now lives on the `central` guard and is covered by
    // Tests\Feature\Platform\CentralAuthTest.

    public function test_the_default_company_is_seeded_with_a_domain(): void
    {
        $this->assertSame(
            config('tenancy.default_domain'),
            $this->seedDefaultCompany()->domain
        );
    }
}

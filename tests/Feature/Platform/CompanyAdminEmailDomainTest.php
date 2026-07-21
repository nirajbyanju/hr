<?php

namespace Tests\Feature\Platform;

use App\Models\CentralUser;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The console asks for the admin's local part only and assigns the company
 * domain to it, so the two can never disagree. A full address is still
 * accepted for callers that post one, and still has to match the domain.
 */
class CompanyAdminEmailDomainTest extends TestCase
{
    use RefreshDatabase;

    private function centralAdmin(): CentralUser
    {
        return CentralUser::query()->create([
            'name' => 'Platform Admin',
            'email' => 'super@example.test',
            'password' => Hash::make('P@ssword123'),
            'is_active' => true,
        ]);
    }

    public function test_the_create_form_shows_the_domain_beside_the_admin_email(): void
    {
        $this->actingAs($this->centralAdmin(), 'central')
            ->get(route('platform.companies.create'))
            ->assertOk()
            ->assertSee('id="admin_email_suffix"', false)
            ->assertSee('id="company_domain"', false);
    }

    /**
     * Provisions a real tenant database, so it cannot be rolled back — the
     * company is deleted at the end, which drops the database and the
     * committed row together.
     */
    public function test_a_local_part_is_completed_with_the_company_domain(): void
    {
        $this->actingAs($this->centralAdmin(), 'central')
            ->post(route('platform.companies.store'), [
                'name' => 'Suffix Co',
                'domain' => 'suffixco.test',
                'admin_email' => 'admin',
                'admin_password' => 'P@ssword123',
                'admin_password_confirmation' => 'P@ssword123',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('platform.dashboard'));

        $company = Company::query()->where('domain', 'suffixco.test')->firstOrFail();

        try {
            $email = $company->run(fn () => User::query()->orderBy('id')->value('email'));

            $this->assertSame('admin@suffixco.test', $email);
        } finally {
            if (tenancy()->initialized) {
                tenancy()->end();
            }

            $company->delete();
        }
    }

    /**
     * Validation rejects the mismatch before any tenant is provisioned, so the
     * company row never needs a database behind it.
     */
    public function test_a_full_address_from_another_domain_is_still_rejected(): void
    {
        $this->actingAs($this->centralAdmin(), 'central')
            ->post(route('platform.companies.store'), [
                'name' => 'Mismatch Co',
                'domain' => 'mismatchco.test',
                'admin_email' => 'admin@elsewhere.test',
                'admin_password' => 'P@ssword123',
                'admin_password_confirmation' => 'P@ssword123',
            ])
            ->assertSessionHasErrors('admin_email');

        $this->assertDatabaseMissing('companies', ['domain' => 'mismatchco.test']);
    }
}

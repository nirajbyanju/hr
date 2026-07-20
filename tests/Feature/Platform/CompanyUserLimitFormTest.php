<?php

namespace Tests\Feature\Platform;

use App\Models\CentralUser;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The seat cap is set from the platform console when a company is created and
 * can be changed afterwards. Covers the console end of the limit; enforcement
 * inside the tenant lives in TenantUserLimitTest.
 */
class CompanyUserLimitFormTest extends TestCase
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

    /**
     * A company row with no database behind it, created without firing
     * TenantCreated.
     *
     * Only safe for paths that never open the tenant connection — it is left in
     * `provisioning` because CompanyController::edit() skips its tenant-admin
     * lookup for that status. Anything reaching the tenant needs
     * provisionedCompany() instead.
     */
    private function rowOnlyCompany(array $attributes = []): Company
    {
        return Company::withoutEvents(function () use ($attributes): Company {
            $company = new Company();

            $company->forceFill(array_merge([
                'id' => (string) Str::uuid(),
                'name' => 'Kathmandu Traders',
                'slug' => 'ktm',
                'domain' => 'ktm.test',
                'status' => 'provisioning',
            ], $attributes));

            $company->save();

            return $company;
        });
    }

    public function test_the_create_form_offers_a_user_limit_field(): void
    {
        $this->actingAs($this->centralAdmin(), 'central')
            ->get(route('platform.companies.create'))
            ->assertOk()
            ->assertSee('User account limit')
            ->assertSee('name="user_limit"', false);
    }

    public function test_the_edit_form_shows_the_current_limit(): void
    {
        $company = $this->rowOnlyCompany(['user_limit' => 25]);

        $this->actingAs($this->centralAdmin(), 'central')
            ->get(route('platform.companies.edit', $company))
            ->assertOk()
            ->assertSee('User account limit')
            ->assertSee('value="25"', false);
    }

    /**
     * Validation runs before the controller touches the tenant database, so a
     * rejected value needs no real tenant behind the row.
     */
    public function test_a_zero_limit_is_rejected(): void
    {
        $company = $this->rowOnlyCompany();

        $this->actingAs($this->centralAdmin(), 'central')
            ->put(route('platform.companies.update', $company), [
                'name' => $company->name,
                'domain' => $company->domain,
                'status' => 'active',
                'user_limit' => 0,
            ])
            ->assertSessionHasErrors('user_limit');
    }

    /**
     * Create-then-update against a real tenant database, in one test because
     * provisioning one is expensive.
     *
     * This test cannot be rolled back: CREATE DATABASE is DDL and MySQL
     * implicitly commits on it, so RefreshDatabase's transaction is already
     * gone by the time the assertions run. Deleting the company at the end
     * fires TenantDeleted, which drops the database and removes the committed
     * row together.
     */
    public function test_the_limit_is_stored_on_create_and_can_be_cleared(): void
    {
        $admin = $this->centralAdmin();

        $this->actingAs($admin, 'central')
            ->post(route('platform.companies.store'), [
                'name' => 'Limit Co',
                'domain' => 'limitco.test',
                'expires_on' => now()->addYear()->format('Y-m-d'),
                'user_limit' => 15,
                'admin_email' => 'admin@limitco.test',
                'admin_password' => 'P@ssword123',
                'admin_password_confirmation' => 'P@ssword123',
            ])
            ->assertRedirect(route('platform.dashboard'));

        $company = Company::query()->where('domain', 'limitco.test')->firstOrFail();

        try {
            $this->assertSame(15, (int) $company->user_limit);
            // The cap must be a real column, not swept into stancl's `data` JSON.
            $this->assertDatabaseHas('companies', ['domain' => 'limitco.test', 'user_limit' => 15]);

            $this->actingAs($admin, 'central')
                ->put(route('platform.companies.update', $company), [
                    'name' => 'Limit Co',
                    'domain' => 'limitco.test',
                    'status' => 'active',
                    'user_limit' => '',
                ])
                ->assertRedirect(route('platform.dashboard'));

            $this->assertNull($company->fresh()->user_limit);
        } finally {
            if (tenancy()->initialized) {
                tenancy()->end();
            }

            $company->delete();
        }
    }
}

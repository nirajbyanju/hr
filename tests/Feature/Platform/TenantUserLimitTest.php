<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use App\Modules\Users\Services\UserManagementService;
use Illuminate\Validation\ValidationException;
use Tests\TenantTestCase;

/**
 * The seat cap the platform sets on a company (companies.user_limit) is
 * enforced inside the tenant database, where the accounts actually live.
 *
 * Runs against a real tenant because the check counts rows on the tenant
 * connection — the whole point is that it reads the tenant's own `users` table
 * and not the cached companies.users_count in central.
 */
class TenantUserLimitTest extends TenantTestCase
{
    private function service(): UserManagementService
    {
        return app(UserManagementService::class);
    }

    /**
     * The user recorded as `approved_by` on everything created here. It has to
     * exist as a real row — that column is a self-referencing foreign key — and
     * it occupies a seat like any other account, which is why the tests size
     * their limits off a live count rather than a hard-coded number.
     */
    private function actor(): User
    {
        $actor = new User();

        $actor->forceFill([
            'name' => 'Company Admin',
            'email' => 'actor@testco.test',
            'password' => bcrypt('P@ssword123'),
            'account_status' => 'active',
            'approved_at' => now(),
        ])->save();

        return $actor;
    }

    /** A payload the service will accept; the email varies per call. */
    private function payload(string $email): array
    {
        return [
            'name' => 'New Staff',
            'email' => $email,
            'password' => 'P@ssword123',
            'account_status' => 'active',
            'role_ids' => [],
        ];
    }

    private function seatsUsed(): int
    {
        return User::query()->count();
    }

    /** Apply a cap to the tenant and re-initialise so the service reads it. */
    private function setLimit(?int $limit): void
    {
        $this->tenant->update(['user_limit' => $limit]);
        tenancy()->initialize($this->tenant->fresh());
    }

    public function test_a_company_without_a_limit_can_keep_creating_accounts(): void
    {
        $actor = $this->actor();
        $this->setLimit(null);

        $this->service()->createUser($this->payload('one@testco.test'), (int) $actor->id);
        $this->service()->createUser($this->payload('two@testco.test'), (int) $actor->id);

        $this->assertDatabaseHas('users', ['email' => 'two@testco.test'], 'tenant');
    }

    public function test_accounts_can_be_created_up_to_the_limit(): void
    {
        $actor = $this->actor();
        $this->setLimit($this->seatsUsed() + 2);

        $this->service()->createUser($this->payload('first@testco.test'), (int) $actor->id);
        $this->service()->createUser($this->payload('second@testco.test'), (int) $actor->id);

        $this->assertDatabaseHas('users', ['email' => 'first@testco.test'], 'tenant');
        $this->assertDatabaseHas('users', ['email' => 'second@testco.test'], 'tenant');
    }

    public function test_creating_an_account_past_the_limit_is_rejected(): void
    {
        $actor = $this->actor();
        $this->setLimit($this->seatsUsed() + 1);

        $this->service()->createUser($this->payload('allowed@testco.test'), (int) $actor->id);

        try {
            $this->service()->createUser($this->payload('blocked@testco.test'), (int) $actor->id);
            $this->fail('Creating a user beyond the seat limit should have thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('email', $e->errors());
        }

        // The rejected account must not have been written before the check.
        $this->assertDatabaseMissing('users', ['email' => 'blocked@testco.test'], 'tenant');
    }

    /**
     * Lowering the cap below the current headcount must not delete anyone; it
     * only stops the next account being created.
     */
    public function test_a_limit_below_the_current_headcount_blocks_new_accounts_only(): void
    {
        $actor = $this->actor();
        $this->setLimit(null);
        $this->service()->createUser($this->payload('existing@testco.test'), (int) $actor->id);

        $before = $this->seatsUsed();
        $this->setLimit(1);

        try {
            $this->service()->createUser($this->payload('nope@testco.test'), (int) $actor->id);
            $this->fail('A cap below the current headcount should still block new accounts.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('email', $e->errors());
        }

        $this->assertSame($before, $this->seatsUsed());
        $this->assertDatabaseHas('users', ['email' => 'existing@testco.test'], 'tenant');
    }
}

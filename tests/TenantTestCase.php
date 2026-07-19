<?php

namespace Tests;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Base case for tests that touch tenant data.
 *
 * A real tenant database is provisioned ONCE per PHPUnit process (create,
 * migrate, seed — several seconds) and reused. Per-test isolation comes from a
 * transaction on the tenant connection, which is effectively free.
 *
 * The transaction is explicit and separate from RefreshDatabase on purpose:
 * RefreshDatabase transacts whichever connection is default at setUp time —
 * central — so tenant writes would escape its rollback and leak between tests.
 */
abstract class TenantTestCase extends TestCase
{
    use RefreshDatabase;

    private const SLUG = 'testco';

    private const DOMAIN = 'testco.test';

    /** Database name of the tenant provisioned for this process. */
    protected static ?string $tenantDatabase = null;

    protected Company $tenant;

    /**
     * Per-test isolation comes from a transaction on the tenant connection.
     *
     * Switching tenants mid-test (tenancy()->initialize on another tenant, or
     * $other->run(...)) purges the `tenant` connection and therefore destroys
     * that transaction along with any uncommitted rows. Tests that do so must
     * set this to false and clean up after themselves.
     */
    protected bool $useTenantTransaction = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = static::$tenantDatabase === null
            ? $this->provisionTenant()
            : $this->reuseTenant();

        tenancy()->initialize($this->tenant);

        if ($this->useTenantTransaction) {
            DB::connection('tenant')->beginTransaction();
        }
    }

    protected function tearDown(): void
    {
        if ($this->useTenantTransaction && tenancy()->initialized) {
            DB::connection('tenant')->rollBack();
        }

        if (tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    /**
     * First test in the process: create the database for real, so migrations
     * and seeders are genuinely exercised.
     */
    private function provisionTenant(): Company
    {
        $prefix = config('tenancy.database.prefix');

        // A crashed previous run can leave the database behind, and stancl
        // refuses to create one that already exists.
        DB::connection('mysql')->statement('DROP DATABASE IF EXISTS `' . $prefix . self::SLUG . '`');

        $company = Company::create([
            'name' => 'Test Co',
            'slug' => self::SLUG,
            'domain' => self::DOMAIN,
            'status' => 'active',
        ]);

        static::$tenantDatabase = $company->getAttribute('tenancy_db_name');

        return $company;
    }

    /**
     * Later tests: RefreshDatabase rolled back the central companies row, but
     * the tenant database itself survives. Recreate the row pointing at it,
     * without firing TenantCreated (which would try to create it again).
     */
    private function reuseTenant(): Company
    {
        return Company::withoutEvents(function (): Company {
            $company = new Company();

            $company->forceFill([
                // withoutEvents also suppresses stancl's id generator, which
                // normally runs on `creating`.
                'id' => (string) Str::uuid(),
                'name' => 'Test Co',
                'slug' => self::SLUG,
                'domain' => self::DOMAIN,
                'status' => 'active',
            ]);

            $company->setInternal('db_name', static::$tenantDatabase);
            $company->save();

            return $company;
        });
    }

    /**
     * A second tenant, with its own database, for cross-tenant isolation tests.
     * Callers are responsible for the cost — this provisions a real database.
     */
    protected function makeSecondTenant(string $slug = 'othertestco', string $domain = 'othertestco.test'): Company
    {
        DB::connection('mysql')->statement(
            'DROP DATABASE IF EXISTS `' . config('tenancy.database.prefix') . $slug . '`'
        );

        return Company::create([
            'name' => 'Other Test Co',
            'slug' => $slug,
            'domain' => $domain,
            'status' => 'active',
        ]);
    }
}

<?php

namespace Tests\Feature\Tenancy;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * The core promise of database-per-tenant: one company's data is not merely
 * filtered out of another's queries — it is in a different database.
 *
 * This test switches tenants mid-test, which purges the tenant connection and
 * would destroy the base class's per-test transaction, so it opts out and
 * cleans up after itself.
 */
class TenantIsolationTest extends TenantTestCase
{
    protected bool $useTenantTransaction = false;

    private ?Company $other = null;

    protected function tearDown(): void
    {
        Employee::query()->where('employee_code', 'TESTCO-1')->forceDelete();

        // Dropping the second tenant's database also removes its data.
        $this->other?->delete();
        $this->other = null;

        parent::tearDown();
    }

    private function employee(string $code, string $first): void
    {
        Employee::forceCreate([
            'employee_code' => $code,
            'first_name' => $first,
            'last_name' => 'Tester',
            'date_of_joining' => '2026-01-01',
            'employment_status' => 'active',
        ]);
    }

    public function test_each_tenant_only_sees_its_own_employees(): void
    {
        $this->employee('TESTCO-1', 'Kanchan');

        $this->other = $this->makeSecondTenant();
        $this->other->run(fn () => $this->employee('OTHER-1', 'Alice'));

        // Back in the original tenant.
        tenancy()->initialize($this->tenant);

        $this->assertSame(['Kanchan'], Employee::query()->pluck('first_name')->all());
        $this->assertSame(
            ['Alice'],
            $this->other->run(fn () => Employee::query()->pluck('first_name')->all())
        );
    }

    public function test_the_two_tenants_use_different_databases(): void
    {
        $this->other = $this->makeSecondTenant();

        $this->assertNotSame(
            $this->tenant->getAttribute('tenancy_db_name'),
            $this->other->getAttribute('tenancy_db_name')
        );

        tenancy()->initialize($this->tenant);
        $mine = DB::connection('tenant')->getDatabaseName();

        $theirs = $this->other->run(fn () => DB::connection('tenant')->getDatabaseName());

        $this->assertNotSame($mine, $theirs);
    }

    public function test_deleting_a_company_drops_its_database(): void
    {
        $other = $this->makeSecondTenant();
        $database = $other->getAttribute('tenancy_db_name');

        $this->assertTrue($this->databaseExists($database));

        tenancy()->end();
        $other->delete();

        $this->assertFalse($this->databaseExists($database));

        tenancy()->initialize($this->tenant);
    }

    private function databaseExists(string $name): bool
    {
        return DB::connection('mysql')
            ->select('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?', [$name]) !== [];
    }
}

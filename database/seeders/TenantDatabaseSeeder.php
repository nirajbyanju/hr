<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeds a freshly-created tenant database with the baseline it needs: the
 * permission catalog, default roles + role permissions, and lookup data.
 *
 * The tenant's admin user is created separately by the provisioning flow, so
 * no default admin account is seeded here.
 */
class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            SystemSettingsSeeder::class,
            // Office baseline (departments, designations, salary grades) MUST run
            // before LeavePolicySeeder: leave policies are seeded per salary grade
            // and that seeder skips everything when no grades exist yet.
            OfficeBaselineSeeder::class,
            LeavePolicySeeder::class,
            TaskLookupSeeder::class,
            TaskTagSeeder::class,
        ]);

        (new AdminUserSeeder())->seedDefaultRolesAndPermissions();
    }
}

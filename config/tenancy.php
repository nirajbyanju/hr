<?php

declare(strict_types=1);

use Stancl\Tenancy\Database\Models\Domain;

return [
    /*
    |--------------------------------------------------------------------------
    | stancl/tenancy — database-per-tenant
    |--------------------------------------------------------------------------
    */
    'tenant_model' => \App\Models\Tenant::class,
    'id_generator' => Stancl\Tenancy\UUIDGenerator::class,
    'domain_model' => Domain::class,

    // Hosts that serve the central (landlord/platform) application.
    'central_domains' => [
        '127.0.0.1',
        'localhost',
    ],

    // Executed when tenancy is initialized (per tenant request).
    'bootstrappers' => [
        Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper::class,
    ],

    'database' => [
        'central_connection' => env('DB_CONNECTION', 'mysql'),
        'template_tenant_connection' => null,

        // Tenant database name = prefix + tenant_id + suffix  (e.g. "tenant_ktm").
        'prefix' => 'tenant_',
        'suffix' => '',

        'managers' => [
            'sqlite' => Stancl\Tenancy\TenantDatabaseManagers\SQLiteDatabaseManager::class,
            'mysql' => Stancl\Tenancy\TenantDatabaseManagers\MySQLDatabaseManager::class,
            'mariadb' => Stancl\Tenancy\TenantDatabaseManagers\MySQLDatabaseManager::class,
            'pgsql' => Stancl\Tenancy\TenantDatabaseManagers\PostgreSQLDatabaseManager::class,
        ],
    ],

    'cache' => [
        'tag_base' => 'tenant',
    ],

    'filesystem' => [
        'suffix_base' => 'tenant',
        'disks' => [
            'local',
            'public',
        ],
        'root_override' => [
            'local' => '%storage_path%/app/',
            'public' => '%storage_path%/app/public/',
        ],
        'suffix_storage_path' => true,
        'asset_helper_tenancy' => true,
    ],

    'redis' => [
        'prefix_base' => 'tenant',
        'prefixed_connections' => [],
    ],

    'features' => [
        // Stancl\Tenancy\Features\UserImpersonation::class,
    ],

    'routes' => true,

    // Tenant databases get the full HR schema. Phase 1 reuses the existing
    // migrations; a dedicated central/tenant split is a later refinement.
    'migration_parameters' => [
        '--force' => true,
        '--path' => [database_path('migrations')],
        '--realpath' => true,
    ],

    'seeder_parameters' => [
        '--class' => 'Database\\Seeders\\TenantDatabaseSeeder',
    ],

    /*
    |--------------------------------------------------------------------------
    | Legacy shared-DB tenancy keys (still used by the current app)
    |--------------------------------------------------------------------------
    | Read by App\Http\Middleware\IdentifyTenant and the platform console while
    | the app is migrated from shared-DB (company_id) to database-per-tenant.
    */
    'domain' => env('TENANCY_DOMAIN', 'localhost'),
    'central_subdomains' => ['www', 'app', 'admin', 'api', 'mail'],
    'default_slug' => env('TENANCY_DEFAULT_SLUG', 'default'),
    'allow_dev_override' => (bool) env('TENANCY_DEV_OVERRIDE', env('APP_ENV') === 'local'),
];

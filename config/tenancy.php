<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | stancl/tenancy — database-per-tenant
    |--------------------------------------------------------------------------
    |
    | App\Models\Company IS the tenant model. There is no `domain_model`:
    | tenants are identified by the domain part of a user's login email, not by
    | the HTTP host, so stancl's Domain model and its host-based resolvers are
    | unused.
    */
    'tenant_model' => \App\Models\Company::class,
    'id_generator' => Stancl\Tenancy\UUIDGenerator::class,

    // Hosts that serve the central (landlord/platform) application.
    'central_domains' => [
        '127.0.0.1',
        'localhost',
    ],

    // Executed when tenancy is initialized (per tenant request).
    //
    // CacheTenancyBootstrapper is deliberately NOT enabled: Stancl's CacheManager
    // calls ->tags() on every cache method, but Laravel's `database` store does not
    // extend TaggableStore (only apc/array/failover/memcached/null/redis do), so it
    // throws on the first cached read. The one cache consumer we have
    // (App\Models\SystemSetting) scopes its own key by tenant instead.
    'bootstrappers' => [
        Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper::class,
    ],

    'database' => [
        'central_connection' => env('DB_CONNECTION', 'mysql'),
        'template_tenant_connection' => 'tenant',

        // Tenant database name = prefix + slug (e.g. "tenant_ktm_group"). The
        // name is derived from the slug rather than the UUID by
        // TenancyServiceProvider::boot(), so databases are identifiable in
        // phpMyAdmin. Once created it is frozen in data->tenancy_db_name.
        'prefix' => env('TENANCY_DB_PREFIX', 'tenant_'),
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

    // No stancl route registration — identification is by email domain, so the
    // host-based InitializeTenancyBy* middleware and the tenant asset route are
    // never used.
    'routes' => false,

    // Tenant databases get ONLY database/migrations/tenant. The central
    // migrations (companies, central_users, sessions, cache, jobs) must never
    // run inside a tenant database.
    'migration_parameters' => [
        '--force' => true,
        '--path' => [database_path('migrations/tenant')],
        '--realpath' => true,
    ],

    'seeder_parameters' => [
        '--class' => 'Database\\Seeders\\TenantDatabaseSeeder',
    ],

    /*
    |--------------------------------------------------------------------------
    | Local development
    |--------------------------------------------------------------------------
    | Allows ?tenant=<slug> to switch the active tenant without logging in.
    | Never enable outside local development.
    |
    | There is no "default company" any more: platform administrators live in
    | the central database on their own guard, so there is no lockout risk that
    | a fallback tenant needs to protect against.
    */
    'allow_dev_override' => (bool) env('TENANCY_DEV_OVERRIDE', env('APP_ENV') === 'local'),
];

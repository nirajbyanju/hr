<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Stancl\JobPipeline\JobPipeline;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\DatabaseConfig;
use Stancl\Tenancy\Events;
use Stancl\Tenancy\Jobs;
use Stancl\Tenancy\Listeners;

class TenancyServiceProvider extends ServiceProvider
{

    public function events()
    {
        return [
            // Tenant events
            Events\CreatingTenant::class => [],
            Events\TenantCreated::class => [
                JobPipeline::make([
                    Jobs\CreateDatabase::class,
                    Jobs\MigrateDatabase::class,
                    Jobs\SeedDatabase::class,
                ])->send(function (Events\TenantCreated $event) {
                    return $event->tenant;
                })
                    // MUST stay synchronous. Queued, CreateDatabase would be
                    // pushed onto the queue and QueueTenancyBootstrapper would
                    // try to initialize tenancy for a tenant whose database does
                    // not exist yet.
                    ->shouldBeQueued(false),
            ],
            Events\SavingTenant::class => [],
            Events\TenantSaved::class => [],
            Events\UpdatingTenant::class => [],
            Events\TenantUpdated::class => [],
            Events\DeletingTenant::class => [],
            Events\TenantDeleted::class => [
                JobPipeline::make([
                    Jobs\DeleteDatabase::class,
                ])->send(function (Events\TenantDeleted $event) {
                    return $event->tenant;
                })->shouldBeQueued(false), // `false` by default, but you probably want to make this `true` for production.
            ],

            // Domain events
            Events\CreatingDomain::class => [],
            Events\DomainCreated::class => [],
            Events\SavingDomain::class => [],
            Events\DomainSaved::class => [],
            Events\UpdatingDomain::class => [],
            Events\DomainUpdated::class => [],
            Events\DeletingDomain::class => [],
            Events\DomainDeleted::class => [],

            // Database events
            Events\DatabaseCreated::class => [],
            Events\DatabaseMigrated::class => [],
            Events\DatabaseSeeded::class => [],
            Events\DatabaseRolledBack::class => [],
            Events\DatabaseDeleted::class => [],

            // Tenancy events
            Events\InitializingTenancy::class => [],
            Events\TenancyInitialized::class => [
                Listeners\BootstrapTenancy::class,
            ],

            Events\EndingTenancy::class => [],
            Events\TenancyEnded::class => [
                Listeners\RevertToCentralContext::class,
            ],

            Events\BootstrappingTenancy::class => [],
            Events\TenancyBootstrapped::class => [],
            Events\RevertingToCentralContext::class => [],
            Events\RevertedToCentralContext::class => [],

            // Resource syncing
            Events\SyncedResourceSaved::class => [
                Listeners\UpdateSyncedResource::class,
            ],

            // Fired only when a synced resource is changed in a different DB than the origin DB (to avoid infinite loops)
            Events\SyncedResourceChangedInForeignDatabase::class => [],
        ];
    }

    public function register()
    {
        //
    }

    public function boot()
    {
        $this->bootEvents();
        $this->nameTenantDatabasesBySlug();
    }

    /**
     * Tenant databases are named from the slug ("tenant_ktm_group") rather than
     * the UUID ("tenant_9f3c1a2e-..."), so they are identifiable in phpMyAdmin.
     *
     * stancl persists the generated name to data->tenancy_db_name when the
     * tenant is created, so the database name stays put even if the slug is
     * later renamed. MySQL caps identifiers at 64 characters.
     */
    protected function nameTenantDatabasesBySlug(): void
    {
        DatabaseConfig::generateDatabaseNamesUsing(function (TenantWithDatabase $tenant): string {
            $slug = Str::of((string) $tenant->getAttribute('slug'))
                ->replace('-', '_')
                ->replaceMatches('/[^a-z0-9_]/i', '')
                ->limit(50, '')
                ->toString();

            if ($slug === '') {
                $slug = 't' . str_replace('-', '_', (string) $tenant->getTenantKey());
            }

            return config('tenancy.database.prefix') . $slug . config('tenancy.database.suffix');
        });
    }

    protected function bootEvents()
    {
        foreach ($this->events() as $event => $listeners) {
            foreach ($listeners as $listener) {
                if ($listener instanceof JobPipeline) {
                    $listener = $listener->toListener();
                }

                Event::listen($event, $listener);
            }
        }
    }

    /*
     | routes/tenant.php and stancl's host-based InitializeTenancyBy* middleware
     | are deliberately absent: tenants are identified by the domain part of the
     | login email, and App\Http\Middleware\InitializeTenancyFromSession handles
     | every subsequent request. Its ordering is pinned in bootstrap/app.php.
     */
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenants. This IS stancl/tenancy's tenants table — App\Models\Company extends
 * their Tenant model — which is why the primary key is a string (UUID) and why
 * the `data` JSON column exists.
 *
 * Lives in the CENTRAL database. Each row owns a separate tenant database whose
 * name is stored in data->tenancy_db_name.
 *
 * The columns declared here must also be listed in Company::getCustomColumns(),
 * or stancl's VirtualColumn sweeps them into `data` on save.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table): void {
            $table->string('id')->primary();

            $table->string('name');
            $table->string('slug')->unique();

            // Staff sign in with an email at this domain; it is how a login
            // resolves which tenant database to authenticate against.
            $table->string('domain')->unique();

            // active | suspended | provisioning
            // `provisioning` marks a half-built tenant. Company::isActive()
            // rejects it, so a tenant whose database failed midway through
            // setup cannot be logged into.
            $table->string('status', 20)->default('provisioning');

            $table->date('starts_on')->nullable();
            $table->date('expires_on')->nullable();

            // Denormalised counts for the platform console. Aggregating live
            // across N tenant databases would mean N connections per page load,
            // and one broken tenant DB would take down the whole dashboard.
            $table->unsignedInteger('users_count')->nullable();
            $table->unsignedInteger('employees_count')->nullable();
            $table->timestamp('stats_synced_at')->nullable();

            $table->timestamps();
            $table->json('data')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};

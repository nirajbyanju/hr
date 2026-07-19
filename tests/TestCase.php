<?php

namespace Tests;

use App\Models\Company;
use App\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Activate the default tenant so factory-created records and the records
        // seen by HTTP requests (via IdentifyTenant) share one company context.
        if (Schema::hasTable('companies')) {
            $company = Company::query()
                ->where('slug', config('tenancy.default_slug', 'default'))
                ->first();

            if ($company !== null) {
                app(Tenancy::class)->set($company);
            }
        }
    }
}

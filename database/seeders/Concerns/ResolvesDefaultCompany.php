<?php

namespace Database\Seeders\Concerns;

use App\Models\Company;

/**
 * Shared helper for seeders that create tenant-owned records. Seeders run with
 * model events muted (WithoutModelEvents), so the BelongsToTenant auto-stamp
 * does not fire — seeded rows must set company_id explicitly to the default
 * company returned here.
 */
trait ResolvesDefaultCompany
{
    protected function defaultCompany(): Company
    {
        return Company::query()->firstOrCreate(
            ['slug' => config('tenancy.default_slug', 'default')],
            [
                'name' => config('app.name', 'SamriddhiHR'),
                'status' => 'active',
            ]
        );
    }

    protected function defaultCompanyId(): int
    {
        return (int) $this->defaultCompany()->id;
    }
}

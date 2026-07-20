<?php

namespace App\Http\Controllers\Auth\Concerns;

use App\Models\Company;

/**
 * Guest auth endpoints (login, register, password reset) all query the `users`
 * table before anyone is signed in, so no tenant has been established for them
 * by the middleware. Each must resolve the company from the submitted email and
 * switch onto that tenant's database first — otherwise the query runs against
 * the central database, which has no `users` table at all.
 */
trait ResolvesTenantFromEmail
{
    /**
     * Switch onto the tenant that owns this email address for the rest of the
     * request. Returns null when no company is registered for the domain.
     */
    protected function activateTenantForEmail(?string $email): ?Company
    {
        $company = Company::findForEmail($email);

        if ($company !== null) {
            tenancy()->initialize($company);
        }

        return $company;
    }
}

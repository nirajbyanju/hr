<?php

namespace App\Http\Controllers\Auth\Concerns;

use App\Models\Company;
use App\Tenancy\Tenancy;

/**
 * Guest auth endpoints (login, register, password reset) query the
 * tenant-scoped User model before anyone is signed in, so IdentifyTenant has
 * not established a tenant for them. Each must resolve the company from the
 * submitted email itself, otherwise the global scope is inactive and the query
 * runs across every tenant.
 */
trait ResolvesTenantFromEmail
{
    /**
     * Activate the tenant that owns this email address for the rest of the
     * request. Returns null when no company is registered for the domain.
     */
    protected function activateTenantForEmail(?string $email): ?Company
    {
        $company = Company::findForEmail($email);

        if ($company !== null) {
            app(Tenancy::class)->set($company);
        }

        return $company;
    }
}

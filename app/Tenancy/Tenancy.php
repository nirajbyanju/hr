<?php

namespace App\Tenancy;

use App\Models\Company;

/**
 * Holds the active tenant (company) for the current request. Bound as a
 * singleton so the global scope, middleware and application code all read the
 * same context.
 */
class Tenancy
{
    private ?Company $company = null;

    public function set(?Company $company): void
    {
        $this->company = $company;
    }

    public function current(): ?Company
    {
        return $this->company;
    }

    public function id(): ?int
    {
        return $this->company?->id;
    }

    public function check(): bool
    {
        return $this->company !== null;
    }

    public function forget(): void
    {
        $this->company = null;
    }
}

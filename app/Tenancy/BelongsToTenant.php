<?php

namespace App\Tenancy;

use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scopes a model to the active tenant.
 *
 *  - Reads: a global scope filters every query by company_id when a tenant is
 *    active. With no tenant (CLI, seeders, landlord) nothing is filtered.
 *  - Writes: new records are stamped with the active company_id automatically.
 *
 * Escape hatch: Model::withoutGlobalScope('tenant') for cross-tenant queries.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $tenancy = app(Tenancy::class);

            if ($tenancy->check()) {
                $builder->where(
                    $builder->getModel()->getTable() . '.company_id',
                    $tenancy->id()
                );
            }
        });

        static::creating(function (Model $model): void {
            $tenancy = app(Tenancy::class);

            if ($tenancy->check() && empty($model->getAttribute('company_id'))) {
                $model->setAttribute('company_id', $tenancy->id());
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}

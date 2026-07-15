<?php

namespace App\Modules\SalaryGrades\Repositories;

use App\Models\SalaryGrade;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SalaryGradeRepository
{
    /**
     * @param array<string, mixed> $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $q = trim((string) ($filters['q'] ?? ''));
        $status = (string) ($filters['status'] ?? '');
        $perPage = max(10, min(100, (int) ($filters['per_page'] ?? 20)));

        return SalaryGrade::query()
            ->withCount(['employees', 'leavePolicies'])
            ->when($q !== '', function ($query) use ($q): void {
                $query->where(function ($inner) use ($q): void {
                    $inner
                        ->where('grade_name', 'like', "%{$q}%")
                        ->orWhere('grade_code', 'like', "%{$q}%")
                        ->orWhere('band_name', 'like', "%{$q}%")
                        ->orWhere('description', 'like', "%{$q}%");
                });
            })
            ->when($status !== '', fn ($query) => $query->where('is_active', $status === 'active'))
            ->orderBy('grade_name')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function create(array $attributes): SalaryGrade
    {
        return SalaryGrade::query()->create($attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function update(SalaryGrade $salaryGrade, array $attributes): void
    {
        $salaryGrade->update($attributes);
    }

    public function delete(SalaryGrade $salaryGrade): void
    {
        $salaryGrade->delete();
    }
}

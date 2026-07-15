<?php

namespace App\Modules\SalaryGrades\Services;

use App\Models\SalaryGrade;
use App\Modules\SalaryGrades\Repositories\SalaryGradeRepository;
use Illuminate\Support\Facades\DB;

class SalaryGradeService
{
    public function __construct(private readonly SalaryGradeRepository $salaryGradeRepository)
    {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function createSalaryGrade(array $payload): SalaryGrade
    {
        return DB::transaction(function () use ($payload): SalaryGrade {
            return $this->salaryGradeRepository->create([
                'grade_name' => $payload['grade_name'],
                'grade_code' => $payload['grade_code'],
                'band_name' => $payload['band_name'] ?? null,
                'min_salary' => $payload['min_salary'] ?? null,
                'max_salary' => $payload['max_salary'] ?? null,
                'description' => $payload['description'] ?? null,
                'is_active' => (bool) ($payload['is_active'] ?? true),
            ]);
        });
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function updateSalaryGrade(SalaryGrade $salaryGrade, array $payload): SalaryGrade
    {
        return DB::transaction(function () use ($salaryGrade, $payload): SalaryGrade {
            $this->salaryGradeRepository->update($salaryGrade, [
                'grade_name' => $payload['grade_name'],
                'grade_code' => $payload['grade_code'],
                'band_name' => $payload['band_name'] ?? null,
                'min_salary' => $payload['min_salary'] ?? null,
                'max_salary' => $payload['max_salary'] ?? null,
                'description' => $payload['description'] ?? null,
                'is_active' => (bool) ($payload['is_active'] ?? true),
            ]);

            return $salaryGrade->fresh() ?? $salaryGrade;
        });
    }

    public function deleteSalaryGrade(SalaryGrade $salaryGrade): void
    {
        DB::transaction(function () use ($salaryGrade): void {
            $this->salaryGradeRepository->delete($salaryGrade);
        });
    }
}

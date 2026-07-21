<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Designation;
use App\Models\SalaryGrade;
use Illuminate\Database\Seeder;

/**
 * Seeds the general office baseline a freshly-created tenant needs before it can
 * function: departments, designations, and salary grades. These are the master
 * records every downstream feature depends on — most importantly the salary
 * grades that LeavePolicySeeder attaches its per-grade leave policies to (that
 * seeder skips everything when no grades exist).
 *
 * Idempotent by design: each row is created only when missing (firstOrCreate),
 * so re-running never duplicates rows and never overwrites values HR has since
 * customised through the UI. Runs on every tenant provision, backfilling only
 * the general data that is absent.
 */
class OfficeBaselineSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSalaryGrades();
        $departments = $this->seedDepartments();
        $this->seedDesignations($departments);
    }

    /**
     * Broad pay bands so leave policies (which are per salary grade) have
     * something to attach to out of the box.
     */
    private function seedSalaryGrades(): void
    {
        $grades = [
            ['grade_code' => 'SG1', 'grade_name' => 'Entry Level', 'band_name' => 'Level 1', 'min_salary' => 15000, 'max_salary' => 25000],
            ['grade_code' => 'SG2', 'grade_name' => 'Junior',      'band_name' => 'Level 2', 'min_salary' => 25000, 'max_salary' => 40000],
            ['grade_code' => 'SG3', 'grade_name' => 'Mid Level',   'band_name' => 'Level 3', 'min_salary' => 40000, 'max_salary' => 65000],
            ['grade_code' => 'SG4', 'grade_name' => 'Senior',      'band_name' => 'Level 4', 'min_salary' => 65000, 'max_salary' => 100000],
            ['grade_code' => 'SG5', 'grade_name' => 'Management',  'band_name' => 'Level 5', 'min_salary' => 100000, 'max_salary' => 200000],
        ];

        foreach ($grades as $grade) {
            SalaryGrade::query()->firstOrCreate(
                ['grade_code' => $grade['grade_code']],
                [
                    'grade_name' => $grade['grade_name'],
                    'band_name' => $grade['band_name'],
                    'min_salary' => $grade['min_salary'],
                    'max_salary' => $grade['max_salary'],
                    'description' => null,
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * @return array<string, \App\Models\Department> keyed by department code
     */
    private function seedDepartments(): array
    {
        $departments = [
            ['code' => 'ADMIN', 'name' => 'Administration'],
            ['code' => 'HR',    'name' => 'Human Resources'],
            ['code' => 'FIN',   'name' => 'Finance & Accounts'],
            ['code' => 'IT',    'name' => 'Information Technology'],
            ['code' => 'OPS',   'name' => 'Operations'],
            ['code' => 'SALES', 'name' => 'Sales & Marketing'],
        ];

        $created = [];
        foreach ($departments as $department) {
            $created[$department['code']] = Department::query()->firstOrCreate(
                ['name' => $department['name']],
                [
                    'code' => $department['code'],
                    'description' => null,
                    'is_active' => true,
                ]
            );
        }

        return $created;
    }

    /**
     * @param array<string, \App\Models\Department> $departments keyed by department code
     */
    private function seedDesignations(array $departments): void
    {
        $designations = [
            'ADMIN' => ['Managing Director', 'Office Administrator'],
            'HR'    => ['HR Manager', 'HR Officer'],
            'FIN'   => ['Finance Manager', 'Accountant'],
            'IT'    => ['IT Manager', 'Software Engineer'],
            'OPS'   => ['Operations Manager', 'Operations Executive'],
            'SALES' => ['Sales Manager', 'Marketing Executive'],
        ];

        foreach ($designations as $departmentCode => $titles) {
            $department = $departments[$departmentCode] ?? null;
            if ($department === null) {
                continue;
            }

            foreach ($titles as $title) {
                Designation::query()->firstOrCreate(
                    ['department_id' => $department->id, 'name' => $title],
                    [
                        'code' => null,
                        'description' => null,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}

<?php

namespace Tests\Feature\Tenancy;

use App\Models\Department;
use App\Models\Designation;
use App\Models\LeaveCategory;
use App\Models\LeavePolicy;
use App\Models\SalaryGrade;
use Tests\TenantTestCase;

/**
 * A freshly-provisioned tenant must come with the general office baseline so it
 * is usable immediately: departments, designations, salary grades, and — most
 * importantly — the per-grade leave policies that depend on the grades existing.
 */
class TenantBaselineSeedingTest extends TenantTestCase
{
    public function test_office_baseline_is_seeded_on_tenant_creation(): void
    {
        $this->assertGreaterThanOrEqual(6, Department::query()->count());
        $this->assertGreaterThanOrEqual(1, Designation::query()->count());

        $this->assertTrue(
            Department::query()->where('name', 'Human Resources')->exists(),
            'Expected the Human Resources department to be seeded.'
        );
    }

    public function test_salary_grades_are_seeded(): void
    {
        $this->assertSame(5, SalaryGrade::query()->count());
        $this->assertTrue(SalaryGrade::query()->where('grade_code', 'SG3')->exists());
    }

    public function test_leave_categories_and_per_grade_policies_are_seeded(): void
    {
        // The five default categories from LeavePolicySeeder.
        $this->assertSame(5, LeaveCategory::query()->count());

        // One policy per (category x salary grade): the whole point of running the
        // office baseline before LeavePolicySeeder.
        $categoryCount = LeaveCategory::query()->count();
        $gradeCount = SalaryGrade::query()->count();

        $this->assertSame(
            $categoryCount * $gradeCount,
            LeavePolicy::query()->count(),
            'Every leave category should have a policy for every salary grade.'
        );
    }
}

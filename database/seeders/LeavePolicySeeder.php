<?php

namespace Database\Seeders;

use App\Models\LeaveCategory;
use App\Models\LeavePolicy;
use App\Models\SalaryGrade;
use Illuminate\Database\Seeder;

class LeavePolicySeeder extends Seeder
{
    /**
     * Seed default leave categories and per-salary-grade policies for the current year.
     *
     * Annual and Sick leave accrue monthly (earned leave) with a balance cap; Maternity,
     * Paternity, and Mourning are fixed one-time annual entitlements. Every number here is
     * only a starting default — HR/Admin can change accrual rate, cap, or allocation per
     * category at any time from the Leave Policies screen.
     */
    public function run(): void
    {
        $year = (int) now()->year;

        $salaryGradeIds = SalaryGrade::query()->pluck('id');

        if ($salaryGradeIds->isEmpty()) {
            $this->command?->warn('No salary grades exist yet — skipping leave policy seeding. Run `php artisan db:seed --class=LeavePolicySeeder` again after salary grades are created.');
            return;
        }

        $categories = [
            [
                'code' => 'ANNUAL',
                'name' => 'Annual (Home) Leave',
                'is_paid' => true,
                'requires_attachment' => false,
                'max_consecutive_days' => null,
                'description' => 'Accrues monthly and carries forward up to the policy cap.',
                'policy' => [
                    'days_allocated' => 0,
                    'is_prorated' => false,
                    'carry_forward_mode' => 'full',
                    'carry_forward_limit' => null,
                    'is_earned_leave' => true,
                    'earned_credit_frequency' => 'monthly',
                    'earned_credit_days' => 1.5,
                    'accrual_cap' => 90,
                ],
            ],
            [
                'code' => 'SICK',
                'name' => 'Sick Leave',
                'is_paid' => true,
                'requires_attachment' => true,
                'max_consecutive_days' => null,
                'description' => 'Accrues monthly. Medical certificate required for absences exceeding 3 days.',
                'policy' => [
                    'days_allocated' => 0,
                    'is_prorated' => false,
                    'carry_forward_mode' => 'full',
                    'carry_forward_limit' => null,
                    'is_earned_leave' => true,
                    'earned_credit_frequency' => 'monthly',
                    'earned_credit_days' => 1,
                    'accrual_cap' => 45,
                ],
            ],
            [
                'code' => 'MATERNITY',
                'name' => 'Maternity Leave',
                'is_paid' => true,
                'requires_attachment' => false,
                'max_consecutive_days' => 98,
                'description' => '98 days (14 weeks) for female employees. First 60 days fully paid by employer.',
                'policy' => [
                    'days_allocated' => 98,
                    'is_prorated' => false,
                    'carry_forward_mode' => 'none',
                    'carry_forward_limit' => null,
                    'is_earned_leave' => false,
                    'earned_credit_frequency' => null,
                    'earned_credit_days' => 0,
                    'accrual_cap' => null,
                ],
            ],
            [
                'code' => 'PATERNITY',
                'name' => 'Paternity Leave',
                'is_paid' => true,
                'requires_attachment' => false,
                'max_consecutive_days' => 15,
                'description' => '15 days of fully paid leave for male employees.',
                'policy' => [
                    'days_allocated' => 15,
                    'is_prorated' => false,
                    'carry_forward_mode' => 'none',
                    'carry_forward_limit' => null,
                    'is_earned_leave' => false,
                    'earned_credit_frequency' => null,
                    'earned_credit_days' => 0,
                    'accrual_cap' => null,
                ],
            ],
            [
                'code' => 'MOURNING',
                'name' => 'Mourning Leave',
                'is_paid' => true,
                'requires_attachment' => false,
                'max_consecutive_days' => 13,
                'description' => '13 days of paid leave to observe funeral rites for close family members.',
                'policy' => [
                    'days_allocated' => 13,
                    'is_prorated' => false,
                    'carry_forward_mode' => 'none',
                    'carry_forward_limit' => null,
                    'is_earned_leave' => false,
                    'earned_credit_frequency' => null,
                    'earned_credit_days' => 0,
                    'accrual_cap' => null,
                ],
            ],
        ];

        foreach ($categories as $definition) {
            // Match an existing category by code first; some installs (like this one) already
            // have a category for "Sick Leave"/"Annual (Home) Leave" etc. created manually
            // through the Leave Categories screen under a different code — reuse that row
            // instead of failing on the unique(name) constraint by trying to insert a duplicate.
            $category = LeaveCategory::query()->where('code', $definition['code'])->first()
                ?? LeaveCategory::query()->where('name', $definition['name'])->first();

            $attributes = [
                'name' => $definition['name'],
                'is_paid' => $definition['is_paid'],
                'requires_attachment' => $definition['requires_attachment'],
                'max_consecutive_days' => $definition['max_consecutive_days'],
                'description' => $definition['description'],
                'is_active' => true,
            ];

            if ($category) {
                $category->update($attributes);
            } else {
                $category = LeaveCategory::query()->create($attributes + ['code' => $definition['code']]);
            }

            foreach ($salaryGradeIds as $salaryGradeId) {
                LeavePolicy::query()->updateOrCreate(
                    [
                        'leave_category_id' => $category->id,
                        'salary_grade_id' => $salaryGradeId,
                        'effective_from_year' => $year,
                    ],
                    array_merge($definition['policy'], [
                        'effective_to_year' => null,
                        'is_active' => true,
                        'notes' => null,
                    ])
                );
            }
        }

        $this->command?->info('Leave categories and policies seeded for ' . $year . ' across ' . $salaryGradeIds->count() . ' salary grade(s).');
    }
}

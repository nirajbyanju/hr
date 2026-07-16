<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SalaryGrade;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PermissionSeeder::class);

        $password = (string) config('demo_users.password', 'P@ssword');
        $accounts = (array) config('demo_users.accounts', []);

        $department = $this->demoDepartment();
        $grade = $this->demoSalaryGrade();
        $designations = $this->demoDesignations($department->id);

        $roles = [
            'admin' => $this->role('Admin', 'admin', 'Full demo access'),
            'hr-admin' => $this->role('HR Admin', 'hr-admin', 'HR and payroll demo access'),
            'department-head' => $this->role('Department Head', 'department-head', 'Department approval demo access'),
            'employee' => $this->role('Employee', 'employee', 'Employee self-service demo access'),
        ];

        $this->syncRolePermissions($roles);

        $headEmployee = null;
        foreach ($accounts as $account) {
            $roleSlug = (string) $account['role_slug'];
            $role = $roles[$roleSlug] ?? null;

            if (! $role) {
                continue;
            }

            $user = User::query()->updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => Hash::make($password),
                    'account_status' => 'active',
                    'approved_at' => now(),
                    'rejected_reason' => null,
                ]
            );

            $user->roles()->syncWithoutDetaching([
                $role->id => [
                    'assigned_by' => null,
                    'assigned_at' => now(),
                ],
            ]);

            if ($roleSlug === 'admin') {
                continue;
            }

            $employee = Employee::query()->updateOrCreate(
                ['employee_code' => $account['employee_code']],
                [
                    'user_id' => $user->id,
                    'first_name' => Str::before((string) $account['name'], ' '),
                    'last_name' => Str::after((string) $account['name'], ' '),
                    'gender' => 'other',
                    'phone' => $roleSlug === 'employee' ? '01700000004' : ($roleSlug === 'department-head' ? '01700000003' : '01700000002'),
                    'work_email' => $account['email'],
                    'date_of_joining' => now()->subMonths(8)->toDateString(),
                    'employment_type' => 'full_time',
                    'employment_status' => 'active',
                    'department_id' => $department->id,
                    'designation_id' => $designations[$roleSlug]?->id ?? null,
                    'salary_grade_id' => $grade->id,
                    'reports_to_id' => $roleSlug === 'employee' ? $headEmployee?->id : null,
                ]
            );

            if ($roleSlug === 'department-head') {
                $headEmployee = $employee;
                if (Schema::hasColumn('departments', 'head_employee_id')) {
                    $department->update(['head_employee_id' => $employee->id]);
                }
            }
        }

        if ($headEmployee) {
            Employee::query()
                ->where('employee_code', 'DEMO-EMP')
                ->update(['reports_to_id' => $headEmployee->id]);
        }

        $this->command?->info('Demo users are ready.');
        foreach ($accounts as $account) {
            $this->command?->line($account['label'] . ': ' . $account['email'] . ' / ' . $password);
        }
    }

    private function role(string $name, string $slug, string $description): Role
    {
        return Role::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'description' => $description,
                'is_system' => true,
            ]
        );
    }

    /**
     * @param array<string, Role> $roles
     */
    private function syncRolePermissions(array $roles): void
    {
        $roles['admin']->permissions()->sync(Permission::query()->pluck('id')->all());

        $roles['hr-admin']->permissions()->sync(
            Permission::query()
                ->whereIn('access_scope', ['admin', 'general'])
                ->orWhereIn('group_name', ['dashboard', 'employee', 'attendance', 'leave', 'payroll', 'bonus', 'loan', 'employee_loan', 'loan_installment', 'deduction', 'employee_deduction', 'provident_fund', 'salary_grade', 'salary_template', 'employee_salary', 'payroll_run', 'payslip', 'holiday', 'department', 'designation', 'announcement', 'note', 'notification', 'report'])
                ->pluck('id')
                ->all()
        );

        $roles['department-head']->permissions()->sync(
            Permission::query()
                // leave.manage-balances/manage-quotas/manage-categories are HR-configuration
                // permissions (accrual rates, policy setup) that happen to share the broad
                // 'general' access_scope with many day-to-day permissions. A department head
                // is a stage-one leave approver (leave.approve, granted explicitly below), not
                // an HR administrator, so these must stay excluded even though the scope-based
                // clause below would otherwise sweep them in.
                ->whereNotIn('slug', ['leave.manage-balances', 'leave.manage-quotas', 'leave.manage-categories'])
                ->whereIn('access_scope', ['team', 'self', 'general'])
                ->orWhereIn('slug', [
                    'dashboard.view',
                    'dashboard.view-department',
                    'dashboard.employee-summary',
                    'dashboard.attendance-summary',
                    'dashboard.leave-summary',
                    'dashboard.notice-board',
                    'dashboard.quick-notes',
                    'employee.view-hierarchy',
                    'attendance.view',
                    'leave.view',
                    'leave.approve',
                    'loan.approve-supervisor',
                    'employee_loan.approve-supervisor',
                    'note.view-private',
                    'note.create-private',
                    'note.update-private',
                    'notification.view',
                ])
                ->pluck('id')
                ->all()
        );

        $roles['employee']->permissions()->sync(
            Permission::query()
                ->where('access_scope', 'self')
                ->orWhereIn('slug', [
                    'dashboard.view',
                    'dashboard.view-self',
                    'dashboard.notice-board',
                    'dashboard.quick-notes',
                    'employee.profile-update-request-submit',
                    'employee.resignation-apply',
                    'attendance.clock',
                    'leave.apply',
                    'loan.apply',
                    'employee_loan.apply',
                    'employee_loan.view-own',
                    'note.view-private',
                    'note.create-private',
                    'note.update-private',
                    'notification.view',
                ])
                ->pluck('id')
                ->all()
        );
    }

    private function demoDepartment(): Department
    {
        return Department::query()->updateOrCreate(
            ['code' => 'DEMO-HR'],
            [
                'name' => 'Demo HR Department',
                'description' => 'Demo department for public login users.',
                'is_active' => true,
            ]
        );
    }

    /**
     * @return array<string, Designation>
     */
    private function demoDesignations(int $departmentId): array
    {
        return [
            'hr-admin' => $this->designation($departmentId, 'HR Admin', 'DEMO-HR-ADMIN'),
            'department-head' => $this->designation($departmentId, 'Department Head', 'DEMO-DEPT-HEAD'),
            'employee' => $this->designation($departmentId, 'Employee', 'DEMO-EMPLOYEE'),
        ];
    }

    private function designation(int $departmentId, string $name, string $code): Designation
    {
        return Designation::query()->updateOrCreate(
            [
                'department_id' => $departmentId,
                'name' => $name,
            ],
            [
                'code' => $code,
                'description' => $name . ' demo designation.',
                'is_active' => true,
            ]
        );
    }

    private function demoSalaryGrade(): SalaryGrade
    {
        return SalaryGrade::query()->updateOrCreate(
            ['grade_code' => 'DEMO-GRADE'],
            [
                'grade_name' => 'Demo Grade',
                'band_name' => 'Demo',
                'min_salary' => 30000,
                'max_salary' => 120000,
                'description' => 'Demo salary grade.',
                'is_active' => true,
            ]
        );
    }
}

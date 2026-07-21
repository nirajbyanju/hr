<?php

namespace App\Providers;

use App\Models\Announcement;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveApplication;
use App\Models\SystemSetting;
use App\Models\TaskAssignment;
use App\Models\TaskTransferRequest;
use App\Support\DateSystem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerDateDirectives();

        View::composer('partials.template.sidebar', function ($view): void {
            $user = auth()->user();
            $can = fn (string $permission): bool => $user?->hasPermission($permission) ?? false;
            $canAny = fn (array $permissions): bool => $user?->hasAnyPermission($permissions) ?? false;
            $isEmployeeAdmin = $canAny([
                'employee.view',
                'employee.create',
                'employee.update',
                'employee.delete',
                'department.view',
                'designation.view',
            ]);

            $view->with('sidebarState', [
                'isDashboard' => request()->routeIs('dashboard'),
                'isOrganizationStructure' => request()->routeIs('organization.structure'),
                'isEmployees' => request()->routeIs('employees.*')
                    || request()->routeIs('departments.*')
                    || request()->routeIs('designations.*')
                    || request()->routeIs('employee-resignations.*')
                    || request()->routeIs('employee-statuses.*'),
                'isAttendance' => request()->routeIs('attendance.*'),
                'isAnnouncements' => request()->routeIs('announcements.*'),
                'isLeave' => request()->routeIs('leave-categories.*')
                    || request()->routeIs('leave-policies.*')
                    || request()->routeIs('leave-balances.*')
                    || request()->routeIs('leave-applications.*')
                    || request()->routeIs('leave-approvals.*')
                    || request()->routeIs('leave-reports.*'),
                'isWork' => request()->routeIs('teams.*')
                    || request()->routeIs('projects.*')
                    || request()->routeIs('tasks.*')
                    || request()->routeIs('task-categories.*')
                    || request()->routeIs('task-tags.*'),
                'isHoliday' => request()->routeIs('holidays.*'),
                'isPayroll' => request()->routeIs('payroll.*') || request()->routeIs('salary-grades.*'),
                'isReports' => request()->routeIs('reports.*') || request()->routeIs('leave-reports.*'),
                'isUserManagement' => request()->routeIs('users.*') || request()->routeIs('roles.*') || request()->routeIs('permissions.*'),
                'isSettings' => request()->routeIs('settings.*'),

                'canEmployeeView' => $isEmployeeAdmin && $can('employee.view'),
                'canEmployeeCreate' => $isEmployeeAdmin && $can('employee.create'),
                'canEmployeeUpdate' => $isEmployeeAdmin && $can('employee.update'),
                'canEmployeeProfileUpdateSubmit' => $user?->employee !== null,
                'canResignationApply' => ($user?->employee !== null) && $canAny(['employee.resignation-apply', 'employee.resignation-view']),
                'canResignationSupervisorApprove' => $can('employee.resignation-supervisor-approve'),
                'canResignationFinalApprove' => $can('employee.resignation-final-approve'),
                'canEmployeeStatusView' => $canAny(['employee.status-view', 'employee.status-update']),
                'canDepartmentView' => $isEmployeeAdmin && $can('department.view'),
                'canDepartmentCreate' => $isEmployeeAdmin && $can('department.create'),
                'canDesignationView' => $isEmployeeAdmin && $can('designation.view'),
                'canDesignationCreate' => $isEmployeeAdmin && $can('designation.create'),
                'canHolidayView' => $can('holiday.view'),
                'canHolidayCreate' => $can('holiday.create'),
                'canHolidayUpdate' => $can('holiday.update'),
                'canAttendanceView' => $can('attendance.view'),
                'canAttendanceClock' => $can('attendance.clock'),
                'canAttendanceManage' => $can('attendance.manage'),
                'canAttendanceReport' => $canAny(['attendance.report', 'attendance.export']),
                'canAttendanceApiIntegration' => $canAny(['attendance.api-integration', 'attendance.manage']),
                'canAnnouncementView' => $can('announcement.view'),
                'canAnnouncementCreate' => $can('announcement.create'),
                'canAnnouncementPublish' => $can('announcement.publish'),
                'canAnnouncementApprove' => $can('announcement.approve'),
                'canAnnouncementMenu' => $user !== null,
                'canLeaveView' => $can('leave.view'),
                'canLeaveApply' => $can('leave.apply'),
                'canLeaveApprove' => $can('leave.approve'),
                'canLeaveManageCategories' => $can('leave.manage-categories'),
                'canLeaveManageQuotas' => $canAny(['leave.manage-quotas', 'leave.manage-balances']),
                'canLeaveReport' => $can('leave.report'),
                'canLeaveRoster' => $canAny(['leave.approve', 'leave.report', 'leave.manage-balances', 'leave.view']),
                'canTeamView' => $can('team.view'),
                'canTeamCreate' => $can('team.create'),
                'canTeamUpdate' => $can('team.update'),
                'canTeamManageMembers' => $can('team.manage-members'),
                'canProjectView' => $can('project.view'),
                'canProjectCreate' => $can('project.create'),
                'canProjectUpdate' => $can('project.update'),
                'canProjectManageMembers' => $can('project.manage-members'),
                'canTaskView' => $can('task.view'),
                'canTaskCreate' => $can('task.create'),
                'canTaskUpdate' => $can('task.update'),
                'canTaskAssign' => $can('task.assign'),
                'canTaskComment' => $can('task.comment'),
                'canTaskAdvanceStatus' => $can('task.advance-status'),
                'canTaskComplete' => $can('task.complete'),
                'canTaskAssignTeam' => $can('task.assign-team'),
                'canTaskTransferRequest' => $can('task.transfer-request'),
                'canTaskTransferApprove' => $can('task.transfer-approve'),
                'canTaskTransferView' => $can('task_transfer.view'),
                'canTaskReviewDecide' => $canAny(['task.review-approve', 'task.review-reject']),
                'canTaskReopen' => $can('task.reopen'),
                'canTaskClose' => $can('task.close'),
                'canTaskWatch' => $can('task.watch'),
                'canTaskCommentManage' => $canAny(['task_comment.create', 'task_comment.update', 'task_comment.delete']),
                'canTaskChecklistManage' => $can('task_checklist.manage'),
                'canTaskChecklistCheck' => $canAny(['task_checklist.check', 'task_checklist.manage']),
                'canTaskAttachmentUpload' => $can('task_attachment.upload'),
                'canTaskAttachmentDelete' => $can('task_attachment.delete'),
                'canTaskKanbanView' => $can('task.view'),
                'canTaskCategoryManage' => $canAny(['task.create', 'task.update', 'task.delete']),
                'canPayrollView' => $canAny(['payroll_run.view', 'payroll_run.generate']),
                'canPayrollGenerate' => $can('payroll_run.generate'),
                'canPayrollSalaryGrades' => $canAny(['salary_grade.view', 'salary_grade.create', 'salary_grade.update']),
                'canPayrollSalaryTemplates' => $canAny(['salary_template.view', 'salary_template.create', 'salary_template.update', 'salary_template.assign', 'employee_salary.view', 'employee_salary.list', 'employee_salary.detail', 'employee_salary.assign']),
                'canPayrollManageSalaryTemplates' => $canAny(['salary_grade.view', 'salary_template.view', 'employee_salary.view', 'employee_salary.list', 'employee_salary.detail', 'salary_template.assign', 'employee_salary.assign']),
                'canPayrollManageBonus' => $canAny(['bonus.view', 'bonus.create', 'bonus.generate-batch']),
                'canPayrollManageLoan' => $canAny(['loan.view', 'loan.apply', 'employee_loan.view', 'employee_loan.view-own', 'employee_loan.apply', 'employee_loan.approve-supervisor', 'employee_loan.approve-final', 'loan_installment.view']),
                'canPayrollManageDeduction' => $canAny(['deduction.view', 'deduction.create', 'employee_deduction.view', 'employee_deduction.create']),
                'canPayrollManagePf' => $canAny(['payroll.manage-pf', 'provident_fund.view', 'provident_fund.create', 'provident_fund.update']),
                'canPayrollReport' => $canAny(['payroll.report', 'payslip.view', 'payslip.export', 'salary_revision.view']),
                'canReportMenu' => $canAny(['report.view', 'report.employee', 'report.attendance', 'report.leave', 'report.payroll', 'employee.view', 'attendance.report', 'leave.report', 'payroll.report', 'payslip.view', 'provident_fund.view', 'provident_fund.report', 'payroll.manage-pf']),
                'canEmployeeReport' => $canAny(['report.employee', 'report.view', 'employee.view']),
                'canAttendanceReportMenu' => $canAny(['report.attendance', 'report.view', 'attendance.report', 'attendance.view', 'attendance.manage']),
                'canLeaveReportMenu' => $canAny(['report.leave', 'report.view', 'leave.report', 'leave.approve', 'leave.view']),
                'canPayrollReportMenu' => $canAny(['report.payroll', 'report.view', 'payroll.report', 'payslip.view']),
                'canProvidentFundReportMenu' => $canAny(['provident_fund.view', 'provident_fund.report', 'payroll.manage-pf']),
                'canPayrollMenu' => $canAny([
                    'payroll.view',
                    'payroll.generate',
                    'payroll.manage-salary-templates',
                    'payroll.manage-bonus',
                    'payroll.manage-loan',
                    'payroll.manage-deduction',
                    'payroll.manage-pf',
                    'payroll.report',
                    'bonus.view',
                    'loan.view',
                    'deduction.view',
                    'salary_grade.view',
                    'salary_template.view',
                    'employee_salary.view',
                    'payroll_run.view',
                    'payslip.view',
                    'employee_loan.view',
                    'employee_loan.view-own',
                    'employee_loan.apply',
                    'employee_loan.approve-supervisor',
                    'employee_loan.approve-final',
                    'loan_installment.view',
                    'employee_deduction.view',
                    'provident_fund.view',
                    'salary_revision.view',
                ]),

                'canRoleView' => $can('role.view'),
                'canRoleCreate' => $can('role.create'),
                'canRoleUpdate' => $can('role.update'),
                'canRoleAssign' => $can('role.assign'),

                'canUserList' => $canAny(['role.view', 'role.assign']),
                'canUserCreate' => $can('role.assign'),
                'canPermissionsMenu' => $can('role.update'),
                'canUserManagementMenu' => $canAny(['role.view', 'role.create', 'role.update', 'role.assign']),
                'canSettingsView' => $can('settings.view'),
            ]);
        });

        View::composer('partials.template.topbar', function ($view): void {
            $user = auth()->user();
            $view->with('topbarNotifications', $this->topbarNotifications($user));
        });

        try {
            if (! Schema::hasTable('system_settings')) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        $settings = SystemSetting::autoloaded();

        if (! empty($settings['app_name'])) {
            Config::set('app.name', $settings['app_name']);
        }

        if (! empty($settings['company_logo'])) {
            Config::set('madpos_ui.logo', $settings['company_logo']);
        }

        if (! empty($settings['company_favicon'])) {
            Config::set('madpos_ui.favicon', $settings['company_favicon']);
        }

        /*
         | The tenant's `time_zone` setting is DELIBERATELY not applied here.
         |
         | It used to call date_default_timezone_set(), which mutates
         | process-global PHP state. In a queue worker (QUEUE_CONNECTION=database)
         | or under Octane the process is reused across tenants, so one company's
         | zone leaked into the next company's jobs — a Kathmandu payroll run
         | computed on whatever zone happened to run last.
         |
         | It also made Laravel STORE local time with no offset recorded, so
         | changing the setting silently reinterpreted every historical row.
         |
         | Timestamps are now always stored in UTC and converted for display by
         | App\Support\DateSystem, the same way the Nepali/English calendar
         | choice is applied. Nothing here may change app.timezone.
         */

        if (! empty($settings['mail_mailer'])) {
            Config::set('mail.default', $settings['mail_mailer']);
        }

        Config::set('mail.mailers.smtp.host', $settings['mail_host'] ?? Config::get('mail.mailers.smtp.host'));
        Config::set('mail.mailers.smtp.port', $settings['mail_port'] ?? Config::get('mail.mailers.smtp.port'));
        Config::set('mail.mailers.smtp.username', $settings['mail_username'] ?? Config::get('mail.mailers.smtp.username'));
        Config::set('mail.mailers.smtp.password', $settings['mail_password'] ?? Config::get('mail.mailers.smtp.password'));
        Config::set('mail.mailers.smtp.encryption', $settings['mail_encryption'] ?? Config::get('mail.mailers.smtp.encryption'));
        Config::set('mail.from.address', $settings['mail_from_address'] ?? Config::get('mail.from.address'));
        Config::set('mail.from.name', $settings['mail_from_name'] ?? Config::get('mail.from.name'));

        View::share('appSettings', $settings);
    }

    /**
     * @return array{items: array<int, array{title: string, message: string, time: string, url: string, icon: string}>, count: int}
     */
    private function topbarNotifications($user): array
    {
        if (! $user || ! $this->notificationTablesReady()) {
            return ['items' => [], 'count' => 0];
        }

        $employee = $user->employee;
        $employeeIds = $this->notificationEmployeeScope($user, $employee);
        $today = CarbonImmutable::today();
        $items = collect();

        if (Schema::hasTable('notifications') && $user->hasPermission('notification.view')) {
            $user->unreadNotifications()
                ->latest()
                ->limit(5)
                ->get()
                ->each(function ($notification) use ($items): void {
                    $data = (array) ($notification->data ?? []);
                    $items->push([
                        'title' => (string) ($notification->title ?? $data['title'] ?? 'Notification'),
                        'message' => (string) ($notification->message ?? $data['message'] ?? 'You have a new notification.'),
                        'time' => $notification->created_at?->diffForHumans() ?? 'New',
                        'url' => (string) ($data['url'] ?? '#'),
                        'icon' => (string) ($data['icon'] ?? 'icon-bell'),
                    ]);
                });
        }

        Announcement::query()
            ->where('approval_status', 'approved')
            ->whereNotNull('publish_at')
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->where(function ($query) use ($employee): void {
                $query->where('audience_type', 'all');

                if ($employee) {
                    $query->orWhere(function ($employeeQuery) use ($employee): void {
                        $employeeQuery
                            ->where('audience_type', 'employees')
                            ->whereJsonContains('audience_employee_ids', (int) $employee->id);
                    });
                }
            })
            ->latest('publish_at')
            ->limit(5)
            ->get()
            ->each(function (Announcement $announcement) use ($items): void {
                $items->push([
                    'title' => $announcement->announcement_type === 'notice' ? 'Notice' : 'Announcement',
                    'message' => (string) $announcement->title,
                    'time' => $announcement->publish_at?->diffForHumans() ?? 'Published',
                    'url' => route('announcements.show', $announcement),
                    'icon' => $announcement->announcement_type === 'notice' ? 'icon-bell' : 'icon-doc',
                ]);
            });

        if ($user->hasAnyPermission(['leave.approve', 'leave.view', 'leave.apply'])) {
            LeaveApplication::query()
                ->with('employee')
                ->where('status', 'pending')
                ->when($employeeIds !== null, fn ($query) => $query->whereIn('employee_id', $employeeIds))
                ->latest()
                ->limit(4)
                ->get()
                ->each(function (LeaveApplication $leave) use ($items, $user): void {
                    $employeeName = trim(($leave->employee?->first_name ?? '') . ' ' . ($leave->employee?->last_name ?? ''));
                    $items->push([
                        'title' => 'Leave Request',
                        'message' => ($employeeName !== '' ? $employeeName : 'Employee') . ' has a pending leave request.',
                        'time' => $leave->created_at?->diffForHumans() ?? 'Pending',
                        'url' => $user->hasPermission('leave.approve') ? route('leave-approvals.index') : route('leave-applications.index'),
                        'icon' => 'icon-calendar',
                    ]);
                });
        }

        if ($user->hasAnyPermission(['attendance.view', 'attendance.manage', 'attendance.report'])) {
            $missingCheckout = AttendanceLog::query()
                ->whereDate('attendance_date', $today)
                ->whereNotNull('check_in_at')
                ->whereNull('check_out_at')
                ->when($employeeIds !== null, fn ($query) => $query->whereIn('employee_id', $employeeIds))
                ->count();

            if ($missingCheckout > 0) {
                $items->push([
                    'title' => 'Attendance Alert',
                    'message' => $missingCheckout . ' employee' . ($missingCheckout === 1 ? ' has' : 's have') . ' missing checkout today.',
                    'time' => 'Today',
                    'url' => route('attendance.index'),
                    'icon' => 'icon-clock',
                ]);
            }

            $lateToday = AttendanceLog::query()
                ->whereDate('attendance_date', $today)
                ->where('status', 'late')
                ->when($employeeIds !== null, fn ($query) => $query->whereIn('employee_id', $employeeIds))
                ->distinct('employee_id')
                ->count('employee_id');

            if ($lateToday > 0) {
                $items->push([
                    'title' => 'Late Attendance',
                    'message' => $lateToday . ' employee' . ($lateToday === 1 ? ' is' : 's are') . ' late today.',
                    'time' => 'Today',
                    'url' => route('attendance.index'),
                    'icon' => 'icon-clock',
                ]);
            }
        }

        if ($user->hasPermission('holiday.view')) {
            $holiday = Holiday::query()
                ->whereDate('holiday_date', '>=', $today)
                ->orderBy('holiday_date')
                ->first();

            if ($holiday) {
                $items->push([
                    'title' => 'Upcoming Holiday',
                    'message' => $holiday->title . ' on ' . $holiday->holiday_date?->format('M d, Y') . '.',
                    'time' => $holiday->holiday_date?->diffForHumans() ?? 'Upcoming',
                    'url' => route('holidays.index'),
                    'icon' => 'icon-plane',
                ]);
            }
        }

        if ($employee && $user->hasPermission('task.view') && Schema::hasTable('task_assignments')) {
            TaskAssignment::query()
                ->with('task:id,title,due_date', 'status:id,code')
                ->where('employee_id', (int) $employee->id)
                ->where('is_active', true)
                ->whereHas('status', fn ($q) => $q->whereNotIn('code', ['completed', 'closed', 'rejected']))
                ->whereHas('task', function ($query) use ($today): void {
                    $query->whereNull('due_date')->orWhereDate('due_date', '<=', $today->addDays(7));
                })
                ->orderBy('id')
                ->limit(3)
                ->get()
                ->each(function (TaskAssignment $assignment) use ($items): void {
                    $dueDate = $assignment->task?->due_date;
                    $items->push([
                        'title' => 'Task Reminder',
                        'message' => (string) $assignment->task?->title,
                        'time' => $dueDate ? CarbonImmutable::parse($dueDate)->diffForHumans() : 'No due date',
                        'url' => route('tasks.show', $assignment->task_id),
                        'icon' => 'icon-briefcase',
                    ]);
                });

            TaskAssignment::query()
                ->with('task:id,title')
                ->where('employee_id', (int) $employee->id)
                ->where('is_active', true)
                ->whereHas('status', fn ($q) => $q->where('code', 'assigned'))
                ->orderByDesc('assigned_at')
                ->limit(3)
                ->get()
                ->each(function (TaskAssignment $assignment) use ($items): void {
                    $items->push([
                        'title' => 'New Task Assigned',
                        'message' => (string) $assignment->task?->title . ' was assigned to you.',
                        'time' => $assignment->assigned_at?->diffForHumans() ?? 'Just now',
                        'url' => route('tasks.show', $assignment->task_id),
                        'icon' => 'icon-briefcase',
                    ]);
                });

            TaskAssignment::query()
                ->with('task:id,title')
                ->where('employee_id', (int) $employee->id)
                ->where('is_active', true)
                ->whereHas('status', fn ($q) => $q->where('code', 'changes_requested'))
                ->orderByDesc('updated_at')
                ->limit(3)
                ->get()
                ->each(function (TaskAssignment $assignment) use ($items): void {
                    $items->push([
                        'title' => 'Changes Requested',
                        'message' => (string) $assignment->task?->title . ' was sent back for changes.',
                        'time' => $assignment->updated_at?->diffForHumans() ?? 'Recently',
                        'url' => route('tasks.show', $assignment->task_id),
                        'icon' => 'icon-briefcase',
                    ]);
                });
        }

        if ($employee && $user->hasAnyPermission(['task.transfer-request', 'task.transfer-approve', 'task.assign-team']) && Schema::hasTable('task_transfer_requests')) {
            TaskTransferRequest::query()
                ->with('task:id,title')
                ->where('status', 'pending')
                ->where(function ($query) use ($employee, $user): void {
                    $query->where('to_employee_id', (int) $employee->id);

                    if ($user->hasAnyPermission(['task.transfer-approve', 'task.assign-team'])) {
                        $query->orWhereNotNull('id');
                    }
                })
                ->latest()
                ->limit(3)
                ->get()
                ->each(function (TaskTransferRequest $transfer) use ($items): void {
                    $items->push([
                        'title' => 'Transfer Request',
                        'message' => 'A transfer request is awaiting a decision on "' . ($transfer->task?->title ?? 'a task') . '".',
                        'time' => $transfer->created_at?->diffForHumans() ?? 'Pending',
                        'url' => route('tasks.transfers.inbox'),
                        'icon' => 'icon-share-alt',
                    ]);
                });
        }

        $items = $items
            ->take(10)
            ->values()
            ->all();

        return [
            'items' => $items,
            'count' => count($items),
        ];
    }

    private function notificationTablesReady(): bool
    {
        try {
            return Schema::hasTable('announcements')
                && Schema::hasTable('attendance_logs')
                && Schema::hasTable('leave_applications')
                && Schema::hasTable('holidays')
                && Schema::hasTable('employees');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<int>|null
     */
    private function notificationEmployeeScope($user, ?Employee $employee): ?array
    {
        if (($user?->hasPermission('dashboard.view-all') ?? false) || ($user?->hasPermission('dashboard.view_all') ?? false)) {
            return null;
        }

        if (! $employee) {
            return [];
        }

        $ids = collect([(int) $employee->id]);

        if (($user?->hasPermission('dashboard.view-department') ?? false) || ($user?->hasPermission('dashboard.view_department') ?? false)) {
            if ($employee->department_id) {
                $ids = $ids->merge(
                    Employee::query()
                        ->where('department_id', (int) $employee->department_id)
                        ->pluck('id')
                );
            } else {
                $ids = $ids->merge(
                    Employee::query()
                        ->where('reports_to_id', (int) $employee->id)
                        ->pluck('id')
                );
            }
        }

        if ($user?->hasAnyPermission(['team.view', 'team.manage-members', 'project.view', 'task.assign'])) {
            if (Schema::hasTable('teams') && Schema::hasTable('team_members')) {
                $teamIds = DB::table('teams')
                    ->leftJoin('team_members', 'team_members.team_id', '=', 'teams.id')
                    ->where(function ($query) use ($employee): void {
                        $query->where('teams.lead_employee_id', (int) $employee->id)
                            ->orWhere(function ($memberQuery) use ($employee): void {
                                $memberQuery
                                    ->where('team_members.employee_id', (int) $employee->id)
                                    ->where('team_members.is_active', true);
                            });
                    })
                    ->pluck('teams.id')
                    ->unique();

                if ($teamIds->isNotEmpty()) {
                    $ids = $ids->merge(
                        DB::table('team_members')
                            ->whereIn('team_id', $teamIds)
                            ->where('is_active', true)
                            ->pluck('employee_id')
                    );
                }
            }

            if (Schema::hasTable('projects') && Schema::hasTable('project_members')) {
                $projectIds = DB::table('projects')
                    ->leftJoin('project_members', 'project_members.project_id', '=', 'projects.id')
                    ->where(function ($query) use ($employee): void {
                        $query->where('projects.manager_employee_id', (int) $employee->id)
                            ->orWhere('project_members.employee_id', (int) $employee->id);
                    })
                    ->pluck('projects.id')
                    ->unique();

                if ($projectIds->isNotEmpty()) {
                    $ids = $ids->merge(
                        DB::table('project_members')
                            ->whereIn('project_id', $projectIds)
                            ->pluck('employee_id')
                    );
                }
            }
        }

        return $ids
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Display helpers for stored (A.D.) dates.
     *
     * Views should never format a date column directly — going through these
     * is what makes the company's Nepali/English choice apply everywhere
     * instead of only on the screens someone remembered to update.
     *
     *   @displayDate($employee->date_of_birth)       2083-04-04  /  2026-07-20
     *   @displayDateLong($employee->date_of_birth)   Shrawan 4, 2083  /  Jul 20, 2026
     */
    private function registerDateDirectives(): void
    {
        Blade::directive(
            'displayDate',
            fn (string $expression): string => "<?php echo e(\App\Support\DateSystem::display({$expression})); ?>"
        );

        Blade::directive(
            'displayDateLong',
            fn (string $expression): string => "<?php echo e(\App\Support\DateSystem::displayLong({$expression})); ?>"
        );

        // For stored timestamps where the clock matters (attendance punches,
        // audit trails): shifts UTC into the company's timezone.
        Blade::directive(
            'displayDateTime',
            fn (string $expression): string => "<?php echo e(\App\Support\DateSystem::displayDateTime({$expression})); ?>"
        );
    }
}

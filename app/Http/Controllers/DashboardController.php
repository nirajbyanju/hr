<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\EmployeeLeaveBalance;
use App\Models\Holiday;
use App\Models\LeaveApplication;
use App\Models\PrivateNote;
use App\Modules\Announcements\Repositories\AnnouncementRepository;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly AnnouncementRepository $announcementRepository)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $employee = $user?->employee;
        $permissions = $this->dashboardPermissions($user);
        $scope = $this->dashboardScope($user);
        $scopedEmployeeIds = $this->scopedEmployeeIds($scope, $employee);
        $today = CarbonImmutable::today();
        $monthStart = $today->startOfMonth();
        $monthEnd = $today->endOfMonth();

        $activeEmployeeQuery = Employee::query()
            ->where('employment_status', 'active')
            ->when($scopedEmployeeIds !== null, fn ($query) => $query->whereIn('id', $scopedEmployeeIds));

        $totalEmployees = (clone $activeEmployeeQuery)->count();
        $todayAttendance = AttendanceLog::query()
            ->whereDate('attendance_date', $today)
            ->when($scopedEmployeeIds !== null, fn ($query) => $query->whereIn('employee_id', $scopedEmployeeIds));

        $presentToday = (clone $todayAttendance)->where('status', 'present')->distinct('employee_id')->count('employee_id');
        $lateToday = (clone $todayAttendance)->where('status', 'late')->distinct('employee_id')->count('employee_id');
        $onLeaveToday = LeaveApplication::query()
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->when($scopedEmployeeIds !== null, fn ($query) => $query->whereIn('employee_id', $scopedEmployeeIds))
            ->distinct('employee_id')
            ->count('employee_id');
        $absentToday = max($totalEmployees - $presentToday - $lateToday - $onLeaveToday, 0);
        $pendingLeaveRequests = LeaveApplication::query()
            ->where('status', 'pending')
            ->when($scopedEmployeeIds !== null, fn ($query) => $query->whereIn('employee_id', $scopedEmployeeIds))
            ->count();
        $upcomingHolidays = Holiday::query()
            ->whereDate('holiday_date', '>=', $today)
            ->whereDate('holiday_date', '<=', $today->addDays(45))
            ->count();
        $upcomingBirthdays = $this->upcomingBirthdaysQuery($today, $scopedEmployeeIds)->count();
        $latestAnnouncements = ($permissions['dashboard.notice_board'] ?? false)
            ? $this->announcementRepository->latestPublished(8, $user)
            : collect();

        $selfEmployeeId = $employee?->id ? (int) $employee->id : null;
        $selfAttendance = AttendanceLog::query()
            ->when($selfEmployeeId, fn ($query) => $query->where('employee_id', $selfEmployeeId))
            ->whereBetween('attendance_date', [$monthStart->toDateString(), $monthEnd->toDateString()]);

        $summaryCards = $scope === 'self'
            ? [
                ['permission' => 'dashboard.attendance_summary', 'label' => 'My Present Days This Month', 'value' => $selfEmployeeId ? (clone $selfAttendance)->where('status', 'present')->distinct('attendance_date')->count('attendance_date') : 0, 'icon' => 'icon-check', 'tone' => 'success'],
                ['permission' => 'dashboard.attendance_summary', 'label' => 'My Late Days This Month', 'value' => $selfEmployeeId ? (clone $selfAttendance)->where('status', 'late')->distinct('attendance_date')->count('attendance_date') : 0, 'icon' => 'icon-clock', 'tone' => 'warning'],
                ['permission' => 'dashboard.attendance_summary', 'label' => 'My Absent Days This Month', 'value' => $selfEmployeeId ? (clone $selfAttendance)->where('status', 'absent')->distinct('attendance_date')->count('attendance_date') : 0, 'icon' => 'icon-close', 'tone' => 'danger'],
                ['permission' => 'dashboard.leave_summary', 'label' => 'My Leave Balance', 'value' => $selfEmployeeId ? (float) EmployeeLeaveBalance::query()->where('employee_id', $selfEmployeeId)->where('year', (int) $today->year)->sum('closing_balance') : 0, 'icon' => 'icon-calendar', 'tone' => 'primary'],
                ['permission' => 'dashboard.leave_summary', 'label' => 'My Pending Leave Requests', 'value' => $selfEmployeeId ? LeaveApplication::query()->where('employee_id', $selfEmployeeId)->where('status', 'pending')->count() : 0, 'icon' => 'icon-hourglass', 'tone' => 'info'],
                ['permission' => 'dashboard.upcoming_events_table', 'label' => 'Upcoming Holidays', 'value' => $upcomingHolidays, 'icon' => 'icon-plane', 'tone' => 'neutral'],
                ['permission' => 'dashboard.notice_board', 'label' => 'Notices', 'value' => $latestAnnouncements->count(), 'icon' => 'icon-bell', 'tone' => 'primary'],
            ]
            : [
                ['permission' => 'dashboard.employee_summary', 'label' => $scope === 'department' ? 'My Department Employees' : 'Total Employees', 'value' => $totalEmployees, 'icon' => 'icon-people', 'tone' => 'primary'],
                ['permission' => 'dashboard.attendance_summary', 'label' => 'Present Today', 'value' => $presentToday, 'icon' => 'icon-check', 'tone' => 'success'],
                ['permission' => 'dashboard.attendance_summary', 'label' => 'Absent Today', 'value' => $absentToday, 'icon' => 'icon-close', 'tone' => 'danger'],
                ['permission' => 'dashboard.attendance_summary', 'label' => 'Late Today', 'value' => $lateToday, 'icon' => 'icon-clock', 'tone' => 'warning'],
                ['permission' => 'dashboard.leave_summary', 'label' => 'On Leave Today', 'value' => $onLeaveToday, 'icon' => 'icon-calendar', 'tone' => 'info'],
                ['permission' => 'dashboard.leave_summary', 'label' => 'Pending Leave Requests', 'value' => $pendingLeaveRequests, 'icon' => 'icon-hourglass', 'tone' => 'primary'],
                ['permission' => 'dashboard.upcoming_events_table', 'label' => 'Upcoming Holidays', 'value' => $upcomingHolidays, 'icon' => 'icon-plane', 'tone' => 'neutral'],
                ['permission' => 'dashboard.upcoming_events_table', 'label' => $scope === 'department' ? 'Upcoming Team Birthdays' : 'Upcoming Birthdays', 'value' => $upcomingBirthdays, 'icon' => 'icon-present', 'tone' => 'neutral'],
            ];

        return view('hr.dashboard.dashboard', [
            'dashboardScope' => $scope,
            'dashboardPermissions' => $permissions,
            'summaryCards' => $summaryCards,
            'attendanceSummary' => [
                'present' => $scope === 'self' && $selfEmployeeId ? (clone $selfAttendance)->where('status', 'present')->distinct('attendance_date')->count('attendance_date') : $presentToday,
                'absent' => $scope === 'self' && $selfEmployeeId ? (clone $selfAttendance)->where('status', 'absent')->distinct('attendance_date')->count('attendance_date') : $absentToday,
                'late' => $scope === 'self' && $selfEmployeeId ? (clone $selfAttendance)->where('status', 'late')->distinct('attendance_date')->count('attendance_date') : $lateToday,
                'leave' => $scope === 'self' && $selfEmployeeId ? LeaveApplication::query()->where('employee_id', $selfEmployeeId)->where('status', 'approved')->whereDate('start_date', '<=', $monthEnd)->whereDate('end_date', '>=', $monthStart)->count() : $onLeaveToday,
            ],
            'departmentChart' => $this->departmentChart($scopedEmployeeIds),
            'todayAttendanceRows' => $this->todayAttendanceRows($today, $scopedEmployeeIds, $scope === 'self' ? $selfEmployeeId : null),
            'pendingLeaveRows' => $this->pendingLeaveRows($scopedEmployeeIds, $scope === 'self' ? $selfEmployeeId : null),
            'upcomingEvents' => $this->upcomingEvents($today, $scopedEmployeeIds),
            'basicAlerts' => $this->basicAlerts($absentToday, $pendingLeaveRequests, $today, $scopedEmployeeIds, $scope),
            'latestAnnouncements' => $latestAnnouncements,
            'canViewAnnouncements' => $user?->hasAnyPermission(['announcement.view', 'announcement.create', 'announcement.publish', 'announcement.approve']) ?? false,
            'canCreateAnnouncement' => $user?->hasPermission('announcement.create') ?? false,
            'canViewPrivateNotes' => ($permissions['dashboard.quick_notes'] ?? false) && ($user?->hasPermission('note.view-private') ?? false),
            'canCreatePrivateNotes' => ($permissions['dashboard.quick_notes'] ?? false) && ($user?->hasPermission('note.create-private') ?? false),
            'canUpdatePrivateNotes' => ($permissions['dashboard.quick_notes'] ?? false) && ($user?->hasPermission('note.update-private') ?? false),
            'canDeletePrivateNotes' => ($permissions['dashboard.quick_notes'] ?? false) && ($user?->hasPermission('note.delete-private') ?? false),
            'privateNotes' => $user && ($permissions['dashboard.quick_notes'] ?? false)
                ? PrivateNote::query()
                    ->where('user_id', (int) $user->id)
                    ->orderBy('is_completed')
                    ->orderByDesc('is_pinned')
                    ->orderByDesc('updated_at')
                    ->limit(100)
                    ->get()
                : collect(),
        ]);
    }

    /**
     * @return array<string, bool>
     */
    private function dashboardPermissions($user): array
    {
        $slugs = [
            'dashboard.view_all',
            'dashboard.view_department',
            'dashboard.view_self',
            'dashboard.employee_summary',
            'dashboard.attendance_summary',
            'dashboard.leave_summary',
            'dashboard.notice_board',
            'dashboard.quick_notes',
            'dashboard.basic_alerts',
            'dashboard.attendance_chart',
            'dashboard.department_chart',
            'dashboard.today_attendance_table',
            'dashboard.pending_leave_table',
            'dashboard.upcoming_events_table',
        ];

        $permissions = [];
        foreach ($slugs as $slug) {
            $normalized = str_replace('_', '-', $slug);
            $permissions[$slug] = ($user?->hasPermission($slug) ?? false) || ($user?->hasPermission($normalized) ?? false);
        }

        return $permissions;
    }

    private function dashboardScope($user): string
    {
        if (($user?->hasPermission('dashboard.view_all') ?? false) || ($user?->hasPermission('dashboard.view-all') ?? false)) {
            return 'all';
        }

        if (($user?->hasPermission('dashboard.view_department') ?? false) || ($user?->hasPermission('dashboard.view-department') ?? false)) {
            return 'department';
        }

        return 'self';
    }

    /**
     * @return array<int>|null
     */
    private function scopedEmployeeIds(string $scope, ?Employee $employee): ?array
    {
        if ($scope === 'all') {
            return null;
        }

        if (! $employee) {
            return [];
        }

        if ($scope === 'department' && $employee->department_id) {
            return Employee::query()
                ->where('department_id', (int) $employee->department_id)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
        }

        if ($scope === 'department') {
            return Employee::query()
                ->where('reports_to_id', (int) $employee->id)
                ->orWhere('id', (int) $employee->id)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
        }

        return [(int) $employee->id];
    }

    private function departmentChart(?array $scopedEmployeeIds): Collection
    {
        return Employee::query()
            ->leftJoin('departments', 'departments.id', '=', 'employees.department_id')
            ->where('employees.employment_status', 'active')
            ->when($scopedEmployeeIds !== null, fn ($query) => $query->whereIn('employees.id', $scopedEmployeeIds))
            ->groupBy('departments.name')
            ->orderBy('departments.name')
            ->get([
                DB::raw("COALESCE(departments.name, 'Unassigned') as label"),
                DB::raw('COUNT(employees.id) as value'),
            ]);
    }

    private function todayAttendanceRows(CarbonImmutable $today, ?array $scopedEmployeeIds, ?int $selfEmployeeId): Collection
    {
        return AttendanceLog::query()
            ->with(['employee.department'])
            ->whereDate('attendance_date', $today)
            ->when($scopedEmployeeIds !== null, fn ($query) => $query->whereIn('employee_id', $scopedEmployeeIds))
            ->when($selfEmployeeId, fn ($query) => $query->where('employee_id', $selfEmployeeId))
            ->latest('check_in_at')
            ->limit(10)
            ->get();
    }

    private function pendingLeaveRows(?array $scopedEmployeeIds, ?int $selfEmployeeId): Collection
    {
        return LeaveApplication::query()
            ->with(['employee.department', 'leaveCategory'])
            ->where('status', 'pending')
            ->when($scopedEmployeeIds !== null, fn ($query) => $query->whereIn('employee_id', $scopedEmployeeIds))
            ->when($selfEmployeeId, fn ($query) => $query->where('employee_id', $selfEmployeeId))
            ->oldest('start_date')
            ->limit(10)
            ->get();
    }

    private function upcomingEvents(CarbonImmutable $today, ?array $scopedEmployeeIds): Collection
    {
        $holidays = Holiday::query()
            ->whereDate('holiday_date', '>=', $today)
            ->whereDate('holiday_date', '<=', $today->addDays(45))
            ->orderBy('holiday_date')
            ->limit(6)
            ->get()
            ->toBase()
            ->map(fn (Holiday $holiday): array => [
                'type' => 'Holiday',
                'title' => $holiday->title,
                'date' => $holiday->holiday_date,
                'meta' => $holiday->is_optional ? 'Optional' : 'Company holiday',
            ]);

        $birthdays = $this->upcomingBirthdaysQuery($today, $scopedEmployeeIds)
            ->limit(6)
            ->get()
            ->toBase()
            ->map(fn (Employee $employee): array => [
                'type' => 'Birthday',
                'title' => trim($employee->first_name . ' ' . $employee->last_name),
                'date' => $employee->date_of_birth ? CarbonImmutable::parse($employee->date_of_birth) : null,
                'meta' => $employee->department?->name ?? 'Unassigned',
            ]);

        return $holidays->merge($birthdays)
            ->sortBy(fn (array $event) => $event['date']?->format('md') ?? '9999')
            ->values()
            ->take(10);
    }

    private function upcomingBirthdaysQuery(CarbonImmutable $today, ?array $scopedEmployeeIds)
    {
        $todayMonthDay = $today->format('m-d');
        $endMonthDay = $today->addDays(45)->format('m-d');

        return Employee::query()
            ->with('department')
            ->whereNotNull('date_of_birth')
            ->where('employment_status', 'active')
            ->when($scopedEmployeeIds !== null, fn ($query) => $query->whereIn('id', $scopedEmployeeIds))
            ->where(function ($query) use ($todayMonthDay, $endMonthDay): void {
                if ($todayMonthDay <= $endMonthDay) {
                    $query->whereRaw("DATE_FORMAT(date_of_birth, '%m-%d') BETWEEN ? AND ?", [$todayMonthDay, $endMonthDay]);
                    return;
                }

                $query->whereRaw("DATE_FORMAT(date_of_birth, '%m-%d') >= ?", [$todayMonthDay])
                    ->orWhereRaw("DATE_FORMAT(date_of_birth, '%m-%d') <= ?", [$endMonthDay]);
            })
            ->orderByRaw("DATE_FORMAT(date_of_birth, '%m-%d')");
    }

    private function basicAlerts(int $absentToday, int $pendingLeaveRequests, CarbonImmutable $today, ?array $scopedEmployeeIds, string $scope): array
    {
        $missingCheckout = AttendanceLog::query()
            ->whereDate('attendance_date', $today)
            ->whereNotNull('check_in_at')
            ->whereNull('check_out_at')
            ->when($scopedEmployeeIds !== null, fn ($query) => $query->whereIn('employee_id', $scopedEmployeeIds))
            ->count();

        $upcomingHoliday = Holiday::query()
            ->whereDate('holiday_date', '>=', $today)
            ->orderBy('holiday_date')
            ->first();

        return array_values(array_filter([
            $absentToday > 0 ? ['tone' => 'danger', 'label' => $scope === 'self' ? __('Absent attendance recorded this month.') : __(':count employees absent today.', ['count' => $absentToday])] : null,
            $pendingLeaveRequests > 0 ? ['tone' => 'warning', 'label' => __(':count leave requests pending.', ['count' => $pendingLeaveRequests])] : null,
            $missingCheckout > 0 ? ['tone' => 'info', 'label' => __(':count employees have missing checkout today.', ['count' => $missingCheckout])] : null,
            $upcomingHoliday ? ['tone' => 'neutral', 'label' => __('Upcoming holiday: :title on :date.', ['title' => $upcomingHoliday->title, 'date' => $upcomingHoliday->holiday_date?->format('M d')])] : null,
        ]));
    }

    public function storeQuickNote(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        abort_if(! $user || ! $user->hasPermission('note.create-private'), 403);

        $validated = $request->validate([
            'note_body' => ['required', 'string', 'max:2000'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $noteBody = trim((string) $validated['note_body']);
        $title = trim((string) ($validated['title'] ?? ''));
        if ($title === '') {
            $title = mb_substr($noteBody, 0, 80);
        }

        $note = PrivateNote::query()->create([
            'user_id' => (int) $user->id,
            'title' => $title,
            'note_body' => $noteBody,
            'is_pinned' => false,
            'is_completed' => false,
            'completed_at' => null,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Quick note added.',
                'note' => [
                    'id' => (int) $note->id,
                    'title' => (string) $note->title,
                    'note_body' => (string) $note->note_body,
                    'is_completed' => (bool) $note->is_completed,
                ],
            ]);
        }

        return redirect()->route('dashboard')->with('success', __('Quick note added.'));
    }

    public function toggleQuickNote(Request $request, PrivateNote $privateNote): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        abort_if(! $user || ! $user->hasPermission('note.update-private'), 403);
        abort_if((int) $privateNote->user_id !== (int) $user->id, 403);

        $isCompleted = ! (bool) $privateNote->is_completed;
        $privateNote->update([
            'is_completed' => $isCompleted,
            'completed_at' => $isCompleted ? now() : null,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $isCompleted ? 'Note marked as done.' : 'Note reopened.',
                'note' => [
                    'id' => (int) $privateNote->id,
                    'is_completed' => (bool) $isCompleted,
                ],
            ]);
        }

        return redirect()->route('dashboard')->with('success', $isCompleted ? 'Note marked as done.' : 'Note reopened.');
    }

    public function deleteQuickNote(Request $request, PrivateNote $privateNote): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        abort_if(! $user || ! $user->hasPermission('note.delete-private'), 403);
        abort_if((int) $privateNote->user_id !== (int) $user->id, 403);

        $privateNote->forceDelete();

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Note deleted permanently.',
                'note_id' => (int) $privateNote->id,
            ]);
        }

        return redirect()->route('dashboard')->with('success', __('Note deleted permanently.'));
    }

    public function updateQuickNote(Request $request, PrivateNote $privateNote): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        abort_if(! $user || ! $user->hasPermission('note.update-private'), 403);
        abort_if((int) $privateNote->user_id !== (int) $user->id, 403);

        $validated = $request->validate([
            'note_body' => ['required', 'string', 'max:2000'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $noteBody = trim((string) $validated['note_body']);
        $title = trim((string) ($validated['title'] ?? ''));
        if ($title === '') {
            $title = mb_substr($noteBody, 0, 80);
        }

        $privateNote->update([
            'title' => $title,
            'note_body' => $noteBody,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Note updated.',
                'note' => [
                    'id' => (int) $privateNote->id,
                    'title' => (string) $privateNote->title,
                    'note_body' => (string) $privateNote->note_body,
                ],
            ]);
        }

        return redirect()->route('dashboard')->with('success', __('Note updated.'));
    }
}

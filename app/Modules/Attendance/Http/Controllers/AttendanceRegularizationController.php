<?php

namespace App\Modules\Attendance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\AttendanceRegularization;
use App\Models\Employee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Attendance regularization requests: an employee (or HR) asks to correct a
 * day's clock-in / clock-out. HR approves or rejects. On approval the requested
 * times are written back onto that day's attendance_logs, so the correction
 * actually lands in the attendance data the rest of the system reads.
 */
class AttendanceRegularizationController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $scopedEmployeeIds = $this->hasAllAccess($user) ? null : $this->scopedEmployeeIds($user);

        $filters = [
            'search' => trim((string) $request->input('search', '')),
            'status' => (string) $request->input('status', ''),
            'per_page' => max(6, min(60, (int) $request->input('per_page', 9))),
        ];

        $base = AttendanceRegularization::query()
            ->when($scopedEmployeeIds !== null, fn ($q) => $q->whereIn('employee_id', $scopedEmployeeIds));

        $stats = [
            'total' => (clone $base)->count(),
            'pending' => (clone $base)->where('status', 'pending')->count(),
            'approved' => (clone $base)->where('status', 'approved')->count(),
            'rejected' => (clone $base)->where('status', 'rejected')->count(),
        ];

        $requests = (clone $base)
            ->with(['employee:id,first_name,last_name,employee_code,gender,avatar_path,designation_id', 'employee.designation:id,name', 'reviewedBy:id,name'])
            ->search($filters['search'])
            ->status($filters['status'])
            ->orderByRaw("FIELD(status, 'pending', 'approved', 'rejected')")
            ->orderByDesc('created_at')
            ->paginate($filters['per_page'])
            ->withQueryString();

        return view('hr.attendance.regularizations', [
            'requests' => $requests,
            'stats' => $stats,
            'filters' => $filters,
            'employees' => $this->scopedEmployees($scopedEmployeeIds),
            'canReview' => $user->hasAnyPermission(['attendance.approve_time_change', 'attendance.manage']),
            'canManage' => $user->hasPermission('attendance.manage'),
        ]);
    }

    /** AJAX: the chosen employee's recent attendance days for the modal dropdown. */
    public function employeeRecords(Request $request, Employee $employee): JsonResponse
    {
        $this->authorizeEmployee($request->user(), $employee->id);

        $days = AttendanceLog::query()
            ->where('employee_id', $employee->id)
            ->orderByDesc('attendance_date')
            ->limit(60)
            ->get(['attendance_date', 'check_in_at', 'check_out_at'])
            ->groupBy(fn (AttendanceLog $l): string => Carbon::parse($l->attendance_date)->toDateString())
            ->map(function ($logs, string $date): array {
                $in = $logs->pluck('check_in_at')->filter()->min();
                $out = $logs->pluck('check_out_at')->filter()->max();

                return [
                    'date' => $date,
                    'check_in' => $in ? Carbon::parse($in)->format('H:i') : null,
                    'check_out' => $out ? Carbon::parse($out)->format('H:i') : null,
                ];
            })
            ->values();

        return response()->json($days);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $this->validateRequest($request);
        $this->authorizeEmployee($user, (int) $data['employee_id']);

        [$log, $originalIn, $originalOut] = $this->originalForDay((int) $data['employee_id'], $data['attendance_date']);

        AttendanceRegularization::create([
            'employee_id' => $data['employee_id'],
            'attendance_log_id' => $log?->id,
            'attendance_date' => $data['attendance_date'],
            'original_check_in_at' => $originalIn,
            'original_check_out_at' => $originalOut,
            'requested_check_in_at' => $this->combine($data['attendance_date'], $data['requested_check_in']),
            'requested_check_out_at' => $this->combine($data['attendance_date'], $data['requested_check_out']),
            'reason' => $data['reason'],
            'status' => 'pending',
            'requested_by' => $user->id,
        ]);

        return back()->with('success', __('Regularization request submitted.'));
    }

    public function update(Request $request, AttendanceRegularization $regularization): RedirectResponse
    {
        $this->authorizeEmployee($request->user(), $regularization->employee_id);

        if (! $regularization->isPending()) {
            return back()->with('error', __('Only pending requests can be edited.'));
        }

        $data = $this->validateRequest($request, $regularization->employee_id);

        $regularization->update([
            'attendance_date' => $data['attendance_date'],
            'requested_check_in_at' => $this->combine($data['attendance_date'], $data['requested_check_in']),
            'requested_check_out_at' => $this->combine($data['attendance_date'], $data['requested_check_out']),
            'reason' => $data['reason'],
        ]);

        return back()->with('success', __('Regularization request updated.'));
    }

    public function approve(Request $request, AttendanceRegularization $regularization): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authorizeReview($user);

        if (! $regularization->isPending()) {
            return back()->with('info', __('This request has already been reviewed.'));
        }

        DB::transaction(function () use ($regularization, $user): void {
            $this->applyToAttendance($regularization, $user);

            $regularization->update([
                'status' => 'approved',
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
                'review_remarks' => null,
            ]);
        });

        return back()->with('success', __('Request approved and attendance updated.'));
    }

    public function reject(Request $request, AttendanceRegularization $regularization): RedirectResponse
    {
        $this->authorizeReview($request->user());

        if (! $regularization->isPending()) {
            return back()->with('info', __('This request has already been reviewed.'));
        }

        $remarks = $request->validate(['review_remarks' => ['nullable', 'string', 'max:1000']])['review_remarks'] ?? null;

        $regularization->update([
            'status' => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_remarks' => $remarks,
        ]);

        return back()->with('success', __('Request rejected.'));
    }

    public function destroy(Request $request, AttendanceRegularization $regularization): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Requesters can withdraw their own pending request; managers can delete any.
        if (! $user->hasPermission('attendance.manage')
            && ! ($regularization->isPending() && $regularization->requested_by === $user->id)) {
            abort(403);
        }

        $regularization->delete();

        return back()->with('success', __('Request removed.'));
    }

    /**
     * Write the approved times back onto the day's attendance_logs. The day is
     * normalised to a clean check-in row + check-out row carrying the requested
     * times, so the attendance grid and reports read the corrected values.
     */
    private function applyToAttendance(AttendanceRegularization $regularization, User $reviewer): void
    {
        $date = $regularization->attendance_date->toDateString();

        AttendanceLog::query()
            ->where('employee_id', $regularization->employee_id)
            ->whereDate('attendance_date', $date)
            ->delete();

        $rows = [];
        if ($regularization->requested_check_in_at !== null) {
            $rows[] = $this->logRow($regularization, $reviewer, $date, $regularization->requested_check_in_at, null, 'checkin');
        }
        if ($regularization->requested_check_out_at !== null) {
            $rows[] = $this->logRow($regularization, $reviewer, $date, null, $regularization->requested_check_out_at, 'checkout');
        }

        if ($rows !== []) {
            AttendanceLog::insert($rows);
        }
    }

    /** @return array<string, mixed> */
    private function logRow(AttendanceRegularization $reg, User $reviewer, string $date, $in, $out, string $kind): array
    {
        return [
            'employee_id' => $reg->employee_id,
            'attendance_date' => $date,
            'check_in_at' => $in,
            'check_out_at' => $out,
            'worked_minutes' => 0,
            'status' => 'present',
            'source' => 'regularization-' . $kind,
            'remarks' => 'Regularized: ' . $reg->reason,
            'approved_by' => $reviewer->id,
            'approved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Current first-in / last-out for a day, plus a representative log row.
     *
     * @return array{0:?AttendanceLog,1:?string,2:?string}
     */
    private function originalForDay(int $employeeId, string $date): array
    {
        $logs = AttendanceLog::query()
            ->where('employee_id', $employeeId)
            ->whereDate('attendance_date', $date)
            ->get();

        if ($logs->isEmpty()) {
            return [null, null, null];
        }

        $in = $logs->pluck('check_in_at')->filter()->min();
        $out = $logs->pluck('check_out_at')->filter()->max();

        return [
            $logs->first(),
            $in ? Carbon::parse($in)->toDateTimeString() : null,
            $out ? Carbon::parse($out)->toDateTimeString() : null,
        ];
    }

    private function combine(string $date, ?string $time): ?string
    {
        return $time ? Carbon::parse($date . ' ' . $time)->toDateTimeString() : null;
    }

    /** @return array<string, mixed> */
    private function validateRequest(Request $request, ?int $lockedEmployeeId = null): array
    {
        $rules = [
            'attendance_date' => ['required', 'date_format:Y-m-d'],
            'requested_check_in' => ['nullable', 'date_format:H:i', 'required_without:requested_check_out'],
            'requested_check_out' => ['nullable', 'date_format:H:i', 'required_without:requested_check_in'],
            'reason' => ['required', 'string', 'max:1000'],
        ];

        if ($lockedEmployeeId === null) {
            $rules['employee_id'] = ['required', 'integer', 'exists:employees,id'];
        }

        $data = $request->validate($rules);
        $data['employee_id'] = $lockedEmployeeId ?? (int) $data['employee_id'];

        return $data;
    }

    // --- Scoping ------------------------------------------------------------

    private function hasAllAccess(User $user): bool
    {
        return $user->hasAnyPermission(['attendance.manage', 'attendance.approve_time_change', 'attendance.report']);
    }

    /** @return array<int, int> */
    private function scopedEmployeeIds(User $user): array
    {
        $employee = $user->employee;
        if (! $employee) {
            return [];
        }

        return array_values(array_unique(array_merge(
            [$employee->id],
            $employee->subordinates()->pluck('id')->all()
        )));
    }

    /** @param array<int, int>|null $scopedEmployeeIds */
    private function scopedEmployees(?array $scopedEmployeeIds)
    {
        return Employee::query()
            ->when($scopedEmployeeIds !== null, fn ($q) => $q->whereIn('id', $scopedEmployeeIds))
            ->orderBy('first_name')->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'employee_code']);
    }

    private function authorizeEmployee(User $user, int $employeeId): void
    {
        if ($this->hasAllAccess($user)) {
            return;
        }

        if (! in_array($employeeId, $this->scopedEmployeeIds($user), true)) {
            abort(403);
        }
    }

    private function authorizeReview(User $user): void
    {
        if (! $user->hasAnyPermission(['attendance.approve_time_change', 'attendance.manage'])) {
            abort(403);
        }
    }
}

<?php

namespace App\Modules\Attendance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AttendancePolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * CRUD for attendance policies — the grace periods and overtime rate that
 * describe how each policy treats lateness, early departure and overtime.
 */
class AttendancePolicyController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'search' => trim((string) $request->input('search', '')),
            'status' => (string) $request->input('status', ''),
            'per_page' => max(6, min(60, (int) $request->input('per_page', 9))),
        ];

        $stats = [
            'total' => AttendancePolicy::query()->count(),
            'active' => AttendancePolicy::query()->where('status', 'active')->count(),
            'avg_late_grace' => (int) round((float) AttendancePolicy::query()->avg('late_arrival_grace_minutes')),
            'avg_overtime' => (float) AttendancePolicy::query()->avg('overtime_rate_per_hour'),
        ];

        $policies = AttendancePolicy::query()
            ->search($filters['search'])
            ->status($filters['status'])
            ->orderBy('name')
            ->paginate($filters['per_page'])
            ->withQueryString();

        return view('hr.attendance.policies', [
            'policies' => $policies,
            'stats' => $stats,
            'filters' => $filters,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        AttendancePolicy::create($this->validated($request));

        return back()->with('success', __('Attendance policy created.'));
    }

    public function update(Request $request, AttendancePolicy $policy): RedirectResponse
    {
        $policy->update($this->validated($request));

        return back()->with('success', __('Attendance policy updated.'));
    }

    public function toggleStatus(AttendancePolicy $policy): RedirectResponse
    {
        $policy->update(['status' => $policy->isActive() ? 'inactive' : 'active']);

        return back()->with('success', __("Policy ':name' is now :status.", [
            'name' => $policy->name,
            'status' => $policy->status,
        ]));
    }

    public function destroy(AttendancePolicy $policy): RedirectResponse
    {
        $policy->delete();

        return back()->with('success', __('Attendance policy removed.'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'late_arrival_grace_minutes' => ['required', 'integer', 'min:0', 'max:480'],
            'early_departure_grace_minutes' => ['required', 'integer', 'min:0', 'max:480'],
            'overtime_rate_per_hour' => ['required', 'numeric', 'min:0', 'max:100000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
    }
}

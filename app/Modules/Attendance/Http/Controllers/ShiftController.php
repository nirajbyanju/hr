<?php

namespace App\Modules\Attendance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * CRUD for work shifts — start/end windows with an optional break and grace
 * period, flagged day or night.
 */
class ShiftController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'search' => trim((string) $request->input('search', '')),
            'status' => (string) $request->input('status', ''),
            'per_page' => max(6, min(60, (int) $request->input('per_page', 9))),
        ];

        $stats = [
            'total' => Shift::query()->count(),
            'active' => Shift::query()->where('status', 'active')->count(),
            'night' => Shift::query()->where('is_night_shift', true)->count(),
            'day' => Shift::query()->where('is_night_shift', false)->count(),
        ];

        $shifts = Shift::query()
            ->search($filters['search'])
            ->status($filters['status'])
            ->orderBy('start_time')
            ->paginate($filters['per_page'])
            ->withQueryString();

        return view('hr.attendance.shifts', [
            'shifts' => $shifts,
            'stats' => $stats,
            'filters' => $filters,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Shift::create($this->validated($request));

        return back()->with('success', __('Shift created.'));
    }

    public function update(Request $request, Shift $shift): RedirectResponse
    {
        $shift->update($this->validated($request));

        return back()->with('success', __('Shift updated.'));
    }

    public function toggleStatus(Shift $shift): RedirectResponse
    {
        $shift->update(['status' => $shift->isActive() ? 'inactive' : 'active']);

        return back()->with('success', __("Shift ':name' is now :status.", [
            'name' => $shift->name,
            'status' => $shift->status,
        ]));
    }

    public function destroy(Shift $shift): RedirectResponse
    {
        $shift->delete();

        return back()->with('success', __('Shift removed.'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'break_duration_minutes' => ['required', 'integer', 'min:0', 'max:480'],
            'break_start_time' => ['nullable', 'date_format:H:i'],
            'break_end_time' => ['nullable', 'date_format:H:i'],
            'grace_period_minutes' => ['required', 'integer', 'min:0', 'max:480'],
            'is_night_shift' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $data['is_night_shift'] = $request->boolean('is_night_shift');

        return $data;
    }
}

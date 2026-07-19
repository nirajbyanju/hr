<?php

namespace App\Modules\IdCards\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\EmployeeIdCard;
use App\Modules\Attendance\Services\AttendanceService;
use App\Modules\IdCards\Support\IdCardToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceScanController extends Controller
{
    public function __construct(private readonly AttendanceService $attendanceService)
    {
    }

    public function index(): View
    {
        return view('hr.attendance.scan');
    }

    public function scan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:255'],
        ]);

        $parsed = IdCardToken::parse($validated['token']);
        if ($parsed === null) {
            return $this->fail(__('Unrecognized QR code. Please use a valid employee ID card.'), 422);
        }

        $card = EmployeeIdCard::query()
            ->where('serial', $parsed['serial'])
            ->where('employee_id', $parsed['employee_id'])
            ->with('employee')
            ->first();

        if ($card === null || ! $card->isActive() || $card->employee === null) {
            return $this->fail(__('This card is not active. Please contact HR.'), 422);
        }

        $employee = $card->employee;

        if (in_array($employee->employment_status, ['resigned', 'terminated'], true)) {
            return $this->fail(__('This employee is not active.'), 422);
        }

        // Debounce: ignore a second scan of the same employee within 45 seconds so a
        // double tap does not accidentally check them straight back out.
        $recentlyScanned = AttendanceLog::query()
            ->where('employee_id', $employee->id)
            ->where('created_at', '>=', now()->subSeconds(45))
            ->exists();

        if ($recentlyScanned) {
            return $this->fail(__('Already recorded a moment ago — please wait before scanning again.'), 429);
        }

        $today = now()->format('Y-m-d');
        $entryType = $this->attendanceService->determineNextEntryType($employee->id, $today);

        $this->attendanceService->addManualLog($employee->id, [
            'attendance_date' => $today,
            'entry_type' => $entryType,
            'entry_time' => now()->format('H:i'),
            'remarks' => 'QR card scan',
        ], null, 'qr');

        return response()->json([
            'success' => true,
            'action' => $entryType,
            'employee' => trim($employee->first_name . ' ' . $employee->last_name),
            'employee_code' => $employee->employee_code,
            'time' => now()->format('h:i A'),
            'message' => $entryType === 'checkin' ? __('Checked in') : __('Checked out'),
        ]);
    }

    private function fail(string $message, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}

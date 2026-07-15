<?php

namespace App\Modules\Attendance\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceApiClient;
use App\Models\Employee;
use App\Modules\Attendance\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AttendanceIngestionController extends Controller
{
    public function __construct(private readonly AttendanceService $attendanceService)
    {
    }

    public function bulkStore(Request $request): JsonResponse
    {
        $payload = $request->all();

        $validator = Validator::make($payload, [
            'entries' => ['required', 'array', 'min:1', 'max:1000'],
            'entries.*.employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'entries.*.employee_code' => ['nullable', 'string', 'max:50'],
            'entries.*.attendance_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'entries.*.entry_type' => ['required', 'string'],
            'entries.*.entry_time' => ['required', 'string', 'max:20'],
            'entries.*.remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        /** @var AttendanceApiClient|null $client */
        $client = $request->attributes->get('attendance_api_client');
        $entries = (array) ($payload['entries'] ?? []);
        $codes = collect($entries)->pluck('employee_code')->filter()->map(fn ($v) => strtolower(trim((string) $v)))->unique()->values();
        $employeeCodeMap = Employee::query()
            ->select(['id', 'employee_code'])
            ->whereIn('employee_code', $codes->all())
            ->get()
            ->mapWithKeys(fn (Employee $employee) => [strtolower((string) $employee->employee_code) => (int) $employee->id])
            ->all();

        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($entries as $index => $entry) {
            $entry = is_array($entry) ? $entry : [];
            $employeeId = (int) ($entry['employee_id'] ?? 0);
            if ($employeeId <= 0) {
                $code = strtolower(trim((string) ($entry['employee_code'] ?? '')));
                $employeeId = (int) ($employeeCodeMap[$code] ?? 0);
            }

            $entryType = $this->normalizeEntryType((string) ($entry['entry_type'] ?? ''));
            $attendanceDate = (string) ($entry['attendance_date'] ?? '');
            $entryTime = (string) ($entry['entry_time'] ?? '');
            $remarks = (string) ($entry['remarks'] ?? '');

            if ($employeeId <= 0) {
                $skipped++;
                $errors[] = "Entry #".($index + 1).": employee_id/employee_code not found.";
                continue;
            }

            if (! in_array($entryType, ['checkin', 'checkout'], true)) {
                $skipped++;
                $errors[] = "Entry #".($index + 1).": invalid entry_type.";
                continue;
            }

            if (! $this->attendanceService->isEntryDateTimeValid($attendanceDate, $entryTime)) {
                $skipped++;
                $errors[] = "Entry #".($index + 1).": invalid entry_time format.";
                continue;
            }

            $sourcePrefix = $client ? ('api-' . preg_replace('/[^a-z0-9]+/i', '', strtolower($client->name))) : 'api';
            $this->attendanceService->addManualLog($employeeId, [
                'attendance_date' => $attendanceDate,
                'entry_type' => $entryType,
                'entry_time' => $entryTime,
                'remarks' => $remarks,
            ], null, substr($sourcePrefix, 0, 30));
            $imported++;
        }

        return response()->json([
            'success' => true,
            'message' => 'Attendance ingestion completed.',
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ]);
    }

    private function normalizeEntryType(string $value): string
    {
        $normalized = strtolower(trim($value));
        if (in_array($normalized, ['checkin', 'check-in', 'in'], true)) {
            return 'checkin';
        }
        if (in_array($normalized, ['checkout', 'check-out', 'out'], true)) {
            return 'checkout';
        }

        return $normalized;
    }
}

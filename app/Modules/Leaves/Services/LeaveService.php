<?php

namespace App\Modules\Leaves\Services;

use App\Models\Employee;
use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveCategory;
use App\Models\LeavePolicy;
use App\Modules\Leaves\Repositories\LeaveRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LeaveService
{
    public function __construct(private readonly LeaveRepository $leaveRepository)
    {
    }

    /**
     * @param array<string, mixed> $payload
     * create a new leave category with the provided details in the payload. The payload should include keys such as 'name', 'code', 'is_paid', 'requires_attachment', 'max_consecutive_days', 'description', and 'is_active'. The method will return the created LeaveCategory instance.
     */
    public function createCategory(array $payload): LeaveCategory
    {
        return DB::transaction(function () use ($payload): LeaveCategory {
            return $this->leaveRepository->createCategory([
                'name' => $payload['name'],
                'code' => $payload['code'],
                'is_paid' => (bool) ($payload['is_paid'] ?? true),
                'requires_attachment' => (bool)($payload['requires_attachment'] ?? false),
                'max_consecutive_days' => $payload['max_consecutive_days'] ?? null,
                'description' => $payload['description'] ?? null,
                'is_active' => (bool)($payload['is_active'] ?? true),
            ]);
        });
    }

    /**
     * @param array<string, mixed> $payload
     * update an existing leave category with the provided details in the payload. The method takes a LeaveCategory instance and an array of payload data, which may include keys such as 'name', 'code', 'is_paid', 'requires_attachment', 'max_consecutive_days', 'description', and 'is_active'. The method will return the updated LeaveCategory instance after applying the changes.
     */
    public function updateCategory(LeaveCategory $leaveCategory, array $payload): LeaveCategory
    {
        return DB::transaction(function () use ($leaveCategory, $payload): LeaveCategory {
            $this->leaveRepository->updateCategory($leaveCategory, [
                'name' => $payload['name'],
                'code' => $payload['code'],
                'is_paid' => (bool) ($payload['is_paid'] ?? true),
                'requires_attachment' => (bool)($payload['requires_attachment'] ?? false),
                'max_consecutive_days' => $payload['max_consecutive_days'] ?? null,
                'description' => $payload['description'] ?? null,
                'is_active' => (bool)($payload['is_active'] ?? true),
            ]);

            return $leaveCategory->fresh() ?? $leaveCategory;
        });
    }
    // delete a leave category from the system. The method takes a LeaveCategory instance as a parameter and performs the deletion within a database transaction to ensure data integrity. After executing this method, the specified leave category will be removed from the database.
     public function deleteCategory(LeaveCategory $leaveCategory): void
    {
        DB::transaction(function () use ($leaveCategory): void {
            $this->leaveRepository->deleteCategory($leaveCategory);
        });
    }

    /**
     * @param array<string, mixed> $payload
     * create a new leave policy based on the provided details in the payload. The payload should include keys such as 'leave_category_id', 'salary_grade_id', 'effective_from_year', 'effective_to_year', 'days_allocated', 'is_prorated', 'carry_forward_mode', 'carry_forward_limit', 'is_earned_leave', 'earned_credit_frequency', 'earned_credit_days', 'is_active', and 'notes'. The method will return the created LeavePolicy instance after successfully creating the policy in the database.
     */

    public function createPolicy(array $payload): LeavePolicy
    {
        return DB::transaction(function () use ($payload): LeavePolicy {
            return $this->leaveRepository->createPolicy($this->normalizePolicyPayload($payload));
        });
    }

    /**
     * @param array<string, mixed> $payload
     * update an existing leave policy with the provided details in the payload. The method takes a LeavePolicy instance and an array of payload data, which may include keys such as 'leave_category_id', 'salary_grade_id', 'effective_from_year', 'effective_to_year', 'days_allocated', 'is_prorated', 'carry_forward_mode', 'carry_forward_limit', 'is_earned_leave', 'earned_credit_frequency', 'earned_credit_days', 'is_active', and 'notes'. The method will return the updated LeavePolicy instance after applying the changes and refreshing it from the database to ensure it reflects the latest state.
     */
    public function updatePolicy(LeavePolicy $leavePolicy, array $payload): LeavePolicy
    {
        return DB::transaction(function () use ($leavePolicy, $payload): LeavePolicy {
            $this->leaveRepository->updatePolicy($leavePolicy, $this->normalizePolicyPayload($payload));

            return $leavePolicy->fresh() ?? $leavePolicy;
        });
    }
    // delete a leave policy from the system. The method takes a LeavePolicy instance as a parameter and performs the deletion within a database transaction to ensure data integrity. After executing this method, the specified leave policy will be removed from the database.
    public function deletePolicy(LeavePolicy $leavePolicy): void
    {
        DB::transaction(function () use ($leavePolicy): void {
            $this->leaveRepository->deletePolicy($leavePolicy);
        });
    }

    /**
     * @param array<string, mixed> $filters
     * synchronize leave balances for all employees for a specific year based on the defined leave policies and the provided filters. The method iterates through each employee and their applicable leave policies, calculating the allocated days, carried forward balance, earned/credited days, and availed days to determine the closing balance for each leave category. The synchronization process is performed within a database transaction to ensure data integrity, and the method returns the total number of processed balance records after completion.
     */

    public function syncBalancesForYear(int $year, array $filters = []): int
    {
        return DB::transaction(function () use ($year, $filters): int {
            $processed = 0;
            $employees = $this->leaveRepository->listEmployeesForBalanceSync($year, $filters);

            foreach ($employees as $employee) {
                $policies = $this->leaveRepository->listPoliciesForSalaryGradeAndYear((int) $employee->salary_grade_id, $year);

                foreach ($policies as $policy) {
                    $existing = $this->leaveRepository->findBalanceForYear((int) $employee->id, (int) $policy->leave_category_id, $year);

                    $allocated = $this->calculateAllocatedDays($employee, $policy, $year);
                    $carriedForward = $this->calculateCarriedForward((int) $employee->id, $policy, $year);
                    $earnedCredited = $this->calculateEarnedCredited($employee, $policy, $year);

                    $openingBalance = (float) ($existing?->opening_balance ?? 0);
                    $availed = (float) ($existing?->availed ?? 0);
                    $adjustments = (float) ($existing?->adjustments ?? 0);
                    $cap = $policy->accrual_cap !== null ? (float) $policy->accrual_cap : null;
                    $closing = $this->calculateClosing($openingBalance, $allocated, $carriedForward, $earnedCredited, $availed, $adjustments, $cap);

                    $this->leaveRepository->upsertBalance((int) $employee->id, (int) $policy->leave_category_id, $year, [
                        'leave_policy_id' => $policy->id,
                        'opening_balance' => $openingBalance,
                        'allocated' => $allocated,
                        'carried_forward' => $carriedForward,
                        'earned_credited' => $earnedCredited,
                        'availed' => $availed,
                        'adjustments' => $adjustments,
                        'closing_balance' => $closing,
                    ]);

                    $processed++;
                }
            }

            return $processed;
        });
    }

    /**
     * @param array<string, mixed> $payload
     * update the leave balance for a specific employee and leave category based on the provided payload. The method takes an EmployeeLeaveBalance instance and an array of payload data, which may include keys such as 'opening_balance', 'allocated', 'carried_forward', 'earned_credited', 'availed', and 'adjustments'. The method calculates the new closing balance based on the provided values and updates the balance record in the database within a transaction to ensure data integrity. After applying the updates, the method returns the refreshed EmployeeLeaveBalance instance reflecting the latest state of the balance.
     */
    public function updateBalance(EmployeeLeaveBalance $balance, array $payload): EmployeeLeaveBalance
    {
        return DB::transaction(function () use ($balance, $payload): EmployeeLeaveBalance {
            $opening = (float) ($payload['opening_balance'] ?? $balance->opening_balance ?? 0);
            $allocated = (float) ($payload['allocated'] ?? $balance->allocated ?? 0);
            $carried = (float) ($payload['carried_forward'] ?? $balance->carried_forward ?? 0);
            $earned = (float) ($payload['earned_credited'] ?? $balance->earned_credited ?? 0);
            $availed = (float) ($payload['availed'] ?? $balance->availed ?? 0);
            $adjustments = (float) ($payload['adjustments'] ?? $balance->adjustments ?? 0);
            $cap = $balance->leavePolicy?->accrual_cap !== null ? (float) $balance->leavePolicy->accrual_cap : null;

            $this->leaveRepository->updateBalance($balance, [
                'opening_balance' => $opening,
                'allocated' => $allocated,
                'carried_forward' => $carried,
                'earned_credited' => $earned,
                'availed' => $availed,
                'adjustments' => $adjustments,
                'closing_balance' => $this->calculateClosing($opening, $allocated, $carried, $earned, $availed, $adjustments, $cap),
            ]);

            return $balance->fresh() ?? $balance;
        });
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     * normalize the input payload for creating or updating a leave policy by ensuring that all expected keys are present and have appropriate data types. The method takes an array of payload data, which may include keys such as 'leave_category_id', 'salary_grade_id', 'effective_from_year', 'effective_to_year', 'days_allocated', 'is_prorated', 'carry_forward_mode', 'carry_forward_limit', 'is_earned_leave', 'earned_credit_frequency', 'earned_credit_days', 'is_active', and 'notes'. The method processes the input to convert values to their correct types (e.g., integers, floats, booleans) and sets default values where necessary, returning a normalized array that can be used for database operations when creating or updating a leave policy.
     */
    private function normalizePolicyPayload(array $payload): array
    {
        $isEarnedLeave = (bool) ($payload['is_earned_leave'] ?? false);
        $carryMode = (string) ($payload['carry_forward_mode'] ?? 'none');

        return [
            'leave_category_id' => (int) $payload['leave_category_id'],
            'salary_grade_id' => (int) $payload['salary_grade_id'],
            'effective_from_year' => (int) $payload['effective_from_year'],
            'effective_to_year' => $payload['effective_to_year'] !== null && $payload['effective_to_year'] !== '' ? (int) $payload['effective_to_year'] : null,
            'days_allocated' => (float) $payload['days_allocated'],
            'is_prorated' => (bool) ($payload['is_prorated'] ?? true),
            'carry_forward_mode' => $carryMode,
            'carry_forward_limit' => $carryMode === 'limited' ? (float) ($payload['carry_forward_limit'] ?? 0) : null,
            'is_earned_leave' => $isEarnedLeave,
            'earned_credit_frequency' => $isEarnedLeave ? (string) ($payload['earned_credit_frequency'] ?? 'monthly') : null,
            'earned_credit_days' => $isEarnedLeave ? (float) ($payload['earned_credit_days'] ?? 0) : 0,
            'accrual_cap' => isset($payload['accrual_cap']) && $payload['accrual_cap'] !== '' ? (float) $payload['accrual_cap'] : null,
            'is_active' => (bool) ($payload['is_active'] ?? true),
            'notes' => $payload['notes'] ?? null,
        ];
    }

    // Calculate the allocated leave days for an employee based on the leave policy and the year. The method considers whether the policy is prorated and the employee's date of joining to determine the appropriate number of allocated days for the specified year.
    private function calculateAllocatedDays(Employee $employee, LeavePolicy $policy, int $year): float
    {
        $allocated = (float) $policy->days_allocated;

        if (! $policy->is_prorated) {
            return $this->normalizeComputedDays($allocated);
        }

        $joinDate = Carbon::parse((string) $employee->date_of_joining);
        if ((int) $joinDate->year < $year) {
            return $this->normalizeComputedDays($allocated);
        }

        if ((int) $joinDate->year > $year) {
            return 0.0;
        }

        $daysInYear = Carbon::create($year, 12, 31)->dayOfYear;
        $remainingDays = max(0, Carbon::create($year, 12, 31)->diffInDays($joinDate, false) * -1 + 1);
        if ($remainingDays <= 0 || $daysInYear <= 0) {
            return 0.0;
        }

        return $this->normalizeComputedDays(($allocated * $remainingDays) / $daysInYear);
    }
    // Calculate the carried forward leave balance for an employee based on the leave policy and the year. The method checks the carry forward mode defined in the policy (none, full, or limited) and retrieves the previous year's closing balance to determine how much can be carried forward to the current year according to the policy rules.
    private function calculateCarriedForward(int $employeeId, LeavePolicy $policy, int $year): float
    {
        if ($policy->carry_forward_mode === 'none') {
            return 0.0;
        }

        $previous = $this->leaveRepository->findBalanceForYear($employeeId, (int) $policy->leave_category_id, $year - 1);
        if (! $previous) {
            return 0.0;
        }

        $previousClosing = max(0.0, (float) $previous->closing_balance);

        if ($policy->carry_forward_mode === 'full') {
            return $this->normalizeComputedDays($previousClosing);
        }

        if ($policy->carry_forward_mode === 'limited') {
            $limit = max(0.0, (float) ($policy->carry_forward_limit ?? 0));
            return $this->normalizeComputedDays(min($previousClosing, $limit));
        }

        return 0.0;
    }

    // Calculate the earned or credited leave days for an employee based on the leave policy and the year. The method checks if the policy allows for earned leave and calculates the number of earned or credited days based on the defined frequency (monthly or yearly) and the employee's date of joining, ensuring that the calculation is prorated according to the time worked within the year.
    private function calculateEarnedCredited(Employee $employee, LeavePolicy $policy, int $year): float
    {
        if (! $policy->is_earned_leave) {
            return 0.0;
        }

        $earnedDays = max(0.0, (float) ($policy->earned_credit_days ?? 0));
        if ($earnedDays <= 0) {
            return 0.0;
        }

            $joinDate = Carbon::parse((string) $employee->date_of_joining)->startOfDay();
            $yearStart = Carbon::create($year, 1, 1)->startOfDay();
            $yearEnd = Carbon::create($year, 12, 31)->endOfDay();
            $today = now()->endOfDay();

            $windowStart = $joinDate->greaterThan($yearStart) ? $joinDate : $yearStart;
            $windowEnd = $yearEnd->lessThan($today) ? $yearEnd : $today;
        if ($windowStart->greaterThan($windowEnd)) {
            return 0.0;
        }

        $frequency = (string) ($policy->earned_credit_frequency ?? 'monthly');
        if ($frequency === 'yearly') {
            return $this->normalizeComputedDays($earnedDays);
        }

        $months = max(1, ($windowStart->year * 12 + $windowStart->month) <= ($windowEnd->year * 12 + $windowEnd->month)
            ? (($windowEnd->year * 12 + $windowEnd->month) - ($windowStart->year * 12 + $windowStart->month) + 1)
            : 0);

        return $this->normalizeComputedDays($months * $earnedDays);
    }
    // Calculate the closing leave balance for an employee based on the opening balance, allocated days, carried forward balance, earned/credited days, availed days, and any adjustments. The method sums up the opening balance, allocated days, carried forward balance, earned/credited days, and adjustments, then subtracts the availed days to determine the final closing balance for the leave category. If the policy defines an accrual cap, the result is clamped so the balance never exceeds it.
    private function calculateClosing(
        float $opening,
        float $allocated,
        float $carriedForward,
        float $earnedCredited,
        float $availed,
        float $adjustments,
        ?float $cap = null
    ): float {
        $closing = $this->normalizeComputedDays($opening + $allocated + $carriedForward + $earnedCredited + $adjustments - $availed);

        if ($cap !== null && $cap > 0) {
            $closing = min($closing, $cap);
        }

        return $closing;
    }

    private function normalizeComputedDays(float $days): float
    {
        return max(0.0, round($days, 2));
    }
}

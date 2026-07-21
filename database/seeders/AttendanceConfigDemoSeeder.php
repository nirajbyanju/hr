<?php

namespace Database\Seeders;

use App\Models\AttendancePolicy;
use App\Models\Shift;
use Illuminate\Database\Seeder;

/**
 * Demo attendance policies and work shifts so the Attendance Policies and
 * Shifts pages render like the reference designs.
 *
 * Run inside a tenant context:
 *   $company->run(fn () => (new AttendanceConfigDemoSeeder())->run());
 */
class AttendanceConfigDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPolicies();
        $this->seedShifts();
    }

    private function seedPolicies(): void
    {
        $policies = [
            ['name' => 'Standard Attendance Policy', 'late_arrival_grace_minutes' => 15, 'early_departure_grace_minutes' => 15, 'overtime_rate_per_hour' => 150, 'description' => 'Default attendance policy with standard grace periods and overtime rates'],
            ['name' => 'Flexible Attendance Policy', 'late_arrival_grace_minutes' => 30, 'early_departure_grace_minutes' => 30, 'overtime_rate_per_hour' => 175, 'description' => 'Flexible attendance policy with extended grace periods for remote and flexible workers'],
            ['name' => 'Strict Attendance Policy',   'late_arrival_grace_minutes' => 5,  'early_departure_grace_minutes' => 5,  'overtime_rate_per_hour' => 200, 'description' => 'Strict attendance policy with minimal grace periods for critical operations'],
        ];

        foreach ($policies as $policy) {
            AttendancePolicy::query()->firstOrCreate(['name' => $policy['name']], $policy + ['status' => 'active']);
        }
    }

    private function seedShifts(): void
    {
        $shifts = [
            ['name' => 'Morning Shift', 'start_time' => '09:00', 'end_time' => '18:00', 'break_duration_minutes' => 60, 'grace_period_minutes' => 15, 'is_night_shift' => false, 'description' => 'Standard morning shift for regular office hours'],
            ['name' => 'Evening Shift', 'start_time' => '14:00', 'end_time' => '23:00', 'break_duration_minutes' => 60, 'grace_period_minutes' => 15, 'is_night_shift' => false, 'description' => 'Evening shift for extended business hours'],
            ['name' => 'Night Shift',   'start_time' => '22:00', 'end_time' => '07:00', 'break_duration_minutes' => 60, 'grace_period_minutes' => 15, 'is_night_shift' => true,  'description' => 'Night shift for 24/7 operations and support'],
        ];

        foreach ($shifts as $shift) {
            Shift::query()->firstOrCreate(['name' => $shift['name']], $shift + ['status' => 'active']);
        }
    }
}

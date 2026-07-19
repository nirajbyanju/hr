<?php

namespace App\Modules\IdCards\Services;

use App\Models\Employee;
use App\Models\EmployeeIdCard;
use App\Models\EmployeeIdCardPrintLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IdCardService
{
    /**
     * Issue a fresh ID card for an employee. Any existing active card is revoked
     * so only one card is ever valid at a time (re-issuing invalidates a lost card).
     */
    public function issue(Employee $employee, ?int $userId, ?string $ip = null, ?string $userAgent = null): EmployeeIdCard
    {
        return DB::transaction(function () use ($employee, $userId, $ip, $userAgent): EmployeeIdCard {
            EmployeeIdCard::query()
                ->where('employee_id', $employee->id)
                ->where('status', 'active')
                ->update([
                    'status' => 'revoked',
                    'revoked_at' => now(),
                    'revoked_by' => $userId,
                ]);

            $version = EmployeeIdCard::query()->where('employee_id', $employee->id)->count() + 1;

            $card = EmployeeIdCard::query()->create([
                'employee_id' => $employee->id,
                'card_number' => sprintf('IDC-%s-%02d', $employee->employee_code, $version),
                'serial' => (string) Str::uuid(),
                'status' => 'active',
                'generated_at' => now(),
                'generated_by' => $userId,
                'print_count' => 0,
            ]);

            $this->log($card, 'generated', null, $userId, $ip, $userAgent);

            return $card;
        });
    }

    /**
     * Record a print (HTML) or download (PDF) of a card and bump its counters.
     */
    public function recordPrint(EmployeeIdCard $card, string $format, ?int $userId, ?string $ip = null, ?string $userAgent = null): EmployeeIdCard
    {
        $event = $format === 'pdf' ? 'downloaded' : 'printed';

        return DB::transaction(function () use ($card, $format, $event, $userId, $ip, $userAgent): EmployeeIdCard {
            $card->forceFill([
                'print_count' => $card->print_count + 1,
                'last_printed_at' => now(),
                'last_printed_by' => $userId,
            ])->save();

            $this->log($card, $event, $format, $userId, $ip, $userAgent);

            return $card;
        });
    }

    public function revoke(EmployeeIdCard $card, ?int $userId, ?string $ip = null, ?string $userAgent = null): EmployeeIdCard
    {
        return DB::transaction(function () use ($card, $userId, $ip, $userAgent): EmployeeIdCard {
            $card->forceFill([
                'status' => 'revoked',
                'revoked_at' => now(),
                'revoked_by' => $userId,
            ])->save();

            $this->log($card, 'revoked', null, $userId, $ip, $userAgent);

            return $card;
        });
    }

    private function log(EmployeeIdCard $card, string $event, ?string $format, ?int $userId, ?string $ip, ?string $userAgent): void
    {
        EmployeeIdCardPrintLog::query()->create([
            'employee_id_card_id' => $card->id,
            'employee_id' => $card->employee_id,
            'event' => $event,
            'format' => $format,
            'performed_by' => $userId,
            'ip_address' => $ip,
            'user_agent' => $userAgent ? Str::limit($userAgent, 250, '') : null,
        ]);
    }
}

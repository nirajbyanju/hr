<?php

namespace App\Modules\Holidays\Services;

use App\Models\Holiday;
use App\Modules\Holidays\Repositories\HolidayRepository;
use Illuminate\Support\Facades\DB;

class HolidayService
{
    public function __construct(private readonly HolidayRepository $holidayRepository)
    {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function createHoliday(array $payload): Holiday
    {
        return DB::transaction(function () use ($payload): Holiday {
            return $this->holidayRepository->create([
                'title' => $payload['title'],
                'holiday_date' => $payload['holiday_date'],
                'holiday_type' => $payload['holiday_type'],
                'is_optional' => (bool) ($payload['is_optional'] ?? false),
                'description' => $payload['description'] ?? null,
            ]);
        });
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function updateHoliday(Holiday $holiday, array $payload): Holiday
    {
        return DB::transaction(function () use ($holiday, $payload): Holiday {
            $this->holidayRepository->update($holiday, [
                'title' => $payload['title'],
                'holiday_date' => $payload['holiday_date'],
                'holiday_type' => $payload['holiday_type'],
                'is_optional' => (bool) ($payload['is_optional'] ?? false),
                'description' => $payload['description'] ?? null,
            ]);

            return $holiday->fresh() ?? $holiday;
        });
    }

    public function deleteHoliday(Holiday $holiday): void
    {
        DB::transaction(function () use ($holiday): void {
            $this->holidayRepository->delete($holiday);
        });
    }
}

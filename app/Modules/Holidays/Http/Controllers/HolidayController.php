<?php

namespace App\Modules\Holidays\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Models\SystemSetting;
use App\Modules\Holidays\Http\Requests\StoreHolidayRequest;
use App\Modules\Holidays\Http\Requests\UpdateHolidayRequest;
use App\Modules\Holidays\Repositories\HolidayRepository;
use App\Modules\Holidays\Services\HolidayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class HolidayController extends Controller
{
    public function __construct(
        private readonly HolidayRepository $holidayRepository,
        private readonly HolidayService $holidayService
    ) {
    }

    public function index(Request $request): View
    {
        $year = $this->resolveRequestedYear($request);
        $perPage = max(10, min(100, (int) $request->input('per_page', 20)));
        $holidays = $this->holidayRepository->paginateCurrentYear($year, $perPage);
        $availableYears = collect($this->holidayRepository->availableYears());
        if (! $availableYears->contains($year)) {
            $availableYears->push($year);
        }
        $availableYears = $availableYears->unique()->sortDesc()->values();
        $calendarItems = $this->holidayRepository->listCurrentYear($year)
            ->map(fn (Holiday $holiday): array => [
                'id' => $holiday->id,
                'title' => $holiday->title,
                'holiday_date' => (string) $holiday->holiday_date?->format('Y-m-d'),
                'holiday_type' => $holiday->holiday_type,
                'is_optional' => (bool) $holiday->is_optional,
                'description' => $holiday->description,
            ])
            ->values();

        return view('hr.holidays.index', [
            'year' => $year,
            'holidays' => $holidays,
            'calendarItems' => $calendarItems,
            'perPage' => $perPage,
            'availableYears' => $availableYears,
            'weekendDayIndexes' => $this->resolveWeekendDayIndexes(),
        ]);
    }

    public function create(): View
    {
        return view('hr.holidays.form', [
            'mode' => 'create',
            'defaultDate' => now()->toDateString(),
        ]);
    }

    public function store(StoreHolidayRequest $request): RedirectResponse
    {
        $this->holidayService->createHoliday($request->validated());

        return redirect()->route('holidays.index')->with('success', __('Holiday created successfully.'));
    }

    public function edit(Holiday $holiday): View
    {
        return view('hr.holidays.form', [
            'mode' => 'edit',
            'holiday' => $holiday,
            'defaultDate' => now()->toDateString(),
        ]);
    }

    public function update(UpdateHolidayRequest $request, Holiday $holiday): RedirectResponse
    {
        $this->holidayService->updateHoliday($holiday, $request->validated());

        return redirect()->route('holidays.index')->with('success', __('Holiday updated successfully.'));
    }

    public function destroy(Holiday $holiday): RedirectResponse
    {
        $this->holidayService->deleteHoliday($holiday);

        return redirect()->route('holidays.index')->with('success', __('Holiday deleted successfully.'));
    }

    public function exportCurrentYearCsv(Request $request): Response
    {
        $year = $this->resolveRequestedYear($request);
        $holidays = $this->holidayRepository->listCurrentYear($year);
        $fileName = 'holidays_' . $year . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        $callback = static function () use ($holidays): void {
            $output = fopen('php://output', 'w');
            if ($output === false) {
                return;
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Date', 'Day', 'Title', 'Type', 'Optional', 'Description']);

            foreach ($holidays as $holiday) {
                $date = $holiday->holiday_date instanceof Carbon
                    ? $holiday->holiday_date
                    : Carbon::parse((string) $holiday->holiday_date);

                fputcsv($output, [
                    $date->format('Y-m-d'),
                    $date->format('l'),
                    $holiday->title,
                    $holiday->holiday_type,
                    $holiday->is_optional ? 'Yes' : 'No',
                    $holiday->description ?? '',
                ]);
            }

            fclose($output);
        };

        return response()->streamDownload($callback, $fileName, $headers);
    }

    private function resolveRequestedYear(Request $request): int
    {
        $fallbackYear = (int) now()->year;
        $requestedYear = (int) $request->input('year', $fallbackYear);

        return max(2000, min(2100, $requestedYear));
    }

    /**
     * @return array<int, int>
     */
    private function resolveWeekendDayIndexes(): array
    {
        $configured = (string) SystemSetting::getValue('weekend_days', 'sat,sun');
        $tokens = array_values(array_filter(array_map(
            static fn (string $day): string => strtolower(trim($day)),
            explode(',', $configured)
        )));

        $map = [
            'sun' => 0,
            'mon' => 1,
            'tue' => 2,
            'wed' => 3,
            'thu' => 4,
            'fri' => 5,
            'sat' => 6,
        ];

        $indexes = [];
        foreach ($tokens as $token) {
            if (array_key_exists($token, $map)) {
                $indexes[] = $map[$token];
            }
        }

        $indexes = array_values(array_unique($indexes));

        return $indexes === [] ? [0, 6] : $indexes;
    }
}

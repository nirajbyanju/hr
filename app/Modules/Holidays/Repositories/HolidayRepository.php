<?php

namespace App\Modules\Holidays\Repositories;

use App\Models\Holiday;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class HolidayRepository
{
    /**
     * @return array<int, int>
     */
    public function availableYears(): array
    {
        return Holiday::query()
            ->selectRaw("{$this->yearExpression('holiday_date')} as year")
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($year) => (int) $year)
            ->filter(fn (int $year): bool => $year > 0)
            ->values()
            ->all();
    }

    private function yearExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y', {$column})",
            'pgsql' => "date_part('year', {$column})",
            default => "YEAR({$column})",
        };
    }

    public function paginateCurrentYear(int $year, int $perPage = 20): LengthAwarePaginator
    {
        $perPage = max(10, min(100, $perPage));

        return Holiday::query()
            ->whereYear('holiday_date', $year)
            ->orderBy('holiday_date')
            ->orderBy('title')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return Collection<int, Holiday>
     */
    public function listCurrentYear(int $year): Collection
    {
        return Holiday::query()
            ->whereYear('holiday_date', $year)
            ->orderBy('holiday_date')
            ->orderBy('title')
            ->get();
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function create(array $attributes): Holiday
    {
        return Holiday::query()->create($attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function update(Holiday $holiday, array $attributes): void
    {
        $holiday->update($attributes);
    }

    public function delete(Holiday $holiday): void
    {
        $holiday->delete();
    }
}

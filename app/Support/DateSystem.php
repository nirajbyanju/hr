<?php

namespace App\Support;

use Anuzpandey\LaravelNepaliDate\Constants\NepaliDate as NepaliCalendar;
use Anuzpandey\LaravelNepaliDate\LaravelNepaliDate;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * The calendar a company works in: Bikram Sambat (BS) or Gregorian (AD).
 *
 * Dates are ALWAYS stored as AD in the database. BS is a presentation concern
 * only — storing BS strings would break date columns, indexes, BETWEEN filters
 * and every payroll period calculation, and would make a company that switches
 * calendars unable to read its own history.
 *
 * So every form posts AD (the visible field is a display mirror, see the
 * x-date-field component) and every read converts on the way out through this
 * class. That keeps the entire domain layer calendar-agnostic.
 */
class DateSystem
{
    public const AD = 'ad';

    public const BS = 'bs';

    /** Setting key holding the company's choice. */
    public const SETTING_KEY = 'date_system';

    /** Setting key holding the company's display timezone. */
    public const TIMEZONE_KEY = 'time_zone';

    /**
     * Nepali month names, indexed 1-12 to match the package's month numbering.
     *
     * @var array<int, string>
     */
    public const NEPALI_MONTHS = [
        1 => 'Baisakh', 2 => 'Jestha', 3 => 'Ashadh', 4 => 'Shrawan',
        5 => 'Bhadra', 6 => 'Ashwin', 7 => 'Kartik', 8 => 'Mangsir',
        9 => 'Poush', 10 => 'Magh', 11 => 'Falgun', 12 => 'Chaitra',
    ];

    /**
     * The company's calendar, defaulting to AD.
     *
     * Read through SystemSetting, so this is per-tenant and served from the
     * settings cache rather than hitting the database on every date rendered.
     */
    public static function current(): string
    {
        $value = \App\Models\SystemSetting::getValue(self::SETTING_KEY, self::AD);

        return $value === self::BS ? self::BS : self::AD;
    }

    public static function isNepali(): bool
    {
        return self::current() === self::BS;
    }

    /**
     * The company's display timezone.
     *
     * Timestamps are stored in UTC; this is only ever applied on the way out.
     * An invalid or missing value falls back to UTC rather than throwing — a
     * typo in Settings must not take every page down.
     */
    public static function timezone(): string
    {
        $zone = (string) \App\Models\SystemSetting::getValue(self::TIMEZONE_KEY, 'UTC');

        return in_array($zone, timezone_identifiers_list(), true) ? $zone : 'UTC';
    }

    /**
     * A stored UTC timestamp shifted into the company's timezone.
     *
     * Every display path goes through here, so Nepal's +05:45 offset is applied
     * in exactly one place instead of being re-derived per view.
     */
    public static function inCompanyZone(mixed $date): ?Carbon
    {
        return self::toCarbon($date)?->setTimezone(self::timezone());
    }

    /**
     * A stored timestamp rendered as date + time in the company's zone and
     * calendar — for attendance punches, audit trails and anything where the
     * clock matters as much as the day.
     */
    public static function displayDateTime(mixed $date, string $placeholder = '—'): string
    {
        $local = self::inCompanyZone($date);

        if ($local === null) {
            return $placeholder;
        }

        return self::display($local) . ' ' . $local->format('H:i');
    }

    /** Human label for the active calendar, for hints and settings copy. */
    public static function label(?string $system = null): string
    {
        return ($system ?? self::current()) === self::BS
            ? __('Nepali (Bikram Sambat)')
            : __('English (Gregorian)');
    }

    /**
     * Format a stored AD date for display in the company's calendar.
     *
     * Returns the placeholder for a null date so callers can drop it straight
     * into a table cell. Conversion failures fall back to the AD value rather
     * than throwing — a report must not 500 because one row holds a date
     * outside the BS conversion table.
     */
    public static function display(mixed $date, string $placeholder = '—'): string
    {
        // Shift into the company's zone BEFORE reading the date. 2026-07-20
        // 20:00 UTC is already 2026-07-21 in Kathmandu (+05:45) — formatting the
        // raw UTC value would report the wrong day for anything logged in the
        // evening, which for attendance is the difference between present and
        // absent.
        $carbon = self::inCompanyZone($date);

        if ($carbon === null) {
            return $placeholder;
        }

        if (! self::isNepali()) {
            return $carbon->format('Y-m-d');
        }

        return self::toBs($carbon) ?? $carbon->format('Y-m-d');
    }

    /**
     * Same as display(), but appends the weekday and spells the month, for
     * detail pages where there is room for a friendlier date.
     */
    public static function displayLong(mixed $date, string $placeholder = '—'): string
    {
        $carbon = self::inCompanyZone($date);

        if ($carbon === null) {
            return $placeholder;
        }

        if (! self::isNepali()) {
            return $carbon->format('M d, Y');
        }

        $bs = self::toBs($carbon);

        if ($bs === null) {
            return $carbon->format('M d, Y');
        }

        [$year, $month, $day] = array_map('intval', explode('-', $bs));

        return sprintf('%s %d, %d', self::NEPALI_MONTHS[$month] ?? $month, $day, $year);
    }

    /** Convert a stored AD date to a BS "YYYY-MM-DD" string, or null if it cannot be converted. */
    public static function toBs(mixed $date): ?string
    {
        $carbon = self::toCarbon($date);

        if ($carbon === null) {
            return null;
        }

        try {
            return LaravelNepaliDate::from($carbon->format('Y-m-d'))->toNepaliDate('Y-m-d');
        } catch (Throwable) {
            // Outside the package's conversion range.
            return null;
        }
    }

    /** Convert a BS "YYYY-MM-DD" string back to an AD date string, or null if invalid. */
    public static function toAd(?string $bsDate): ?string
    {
        if ($bsDate === null || trim($bsDate) === '') {
            return null;
        }

        try {
            return LaravelNepaliDate::from(trim($bsDate))->toEnglishDate('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The BS calendar table, reshaped for the browser picker.
     *
     * Emitted once per page rather than fetched per keystroke, and derived from
     * the package's own data so PHP and JS can never disagree about how many
     * days a Nepali month has.
     *
     * Shape: ['minBsYear' => int, 'maxBsYear' => int, 'refAd' => 'Y-m-d',
     *         'monthDays' => [bsYear => [12 ints]], 'months' => [names]]
     *
     * @return array<string, mixed>
     */
    public static function calendarPayload(): array
    {
        $monthDays = [];

        foreach (NepaliCalendar::$CALENDAR_DATA as $row) {
            $year = (int) $row[0];
            $monthDays[$year] = array_map('intval', array_slice($row, 1, 12));
        }

        $years = array_keys($monthDays);
        $minYear = min($years);

        // Anchor: 1st Baisakh of the first year in the table, expressed in AD.
        // The picker counts days forward from here, so it needs exactly one
        // known correspondence rather than its own conversion algorithm.
        $referenceAd = self::toAd($minYear . '-01-01') ?? '1943-04-14';

        return [
            'minBsYear' => $minYear,
            'maxBsYear' => max($years),
            'refAd' => $referenceAd,
            'monthDays' => $monthDays,
            'months' => array_values(self::NEPALI_MONTHS),
        ];
    }

    /** Coerce the many shapes a date reaches a view in into a Carbon, or null. */
    private static function toCarbon(mixed $date): ?Carbon
    {
        if ($date === null || $date === '') {
            return null;
        }

        if ($date instanceof CarbonInterface) {
            return Carbon::instance($date);
        }

        try {
            return Carbon::parse($date);
        } catch (Throwable) {
            return null;
        }
    }
}

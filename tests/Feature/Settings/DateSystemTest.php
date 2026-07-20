<?php

namespace Tests\Feature\Settings;

use App\Models\SystemSetting;
use App\Support\DateSystem;
use Tests\TenantTestCase;

/**
 * The company-wide calendar choice.
 *
 * The invariant under test throughout: dates are STORED as A.D. regardless of
 * the setting. Bikram Sambat is a presentation layer only, so switching the
 * setting must never alter stored data or what a form submits.
 */
class DateSystemTest extends TenantTestCase
{
    private function useCalendar(string $system): void
    {
        SystemSetting::put(DateSystem::SETTING_KEY, $system, ['group_name' => 'localization']);
        SystemSetting::forgetCache();
    }

    private function useTimezone(string $zone): void
    {
        SystemSetting::put(DateSystem::TIMEZONE_KEY, $zone, ['group_name' => 'localization']);
        SystemSetting::forgetCache();
    }

    /**
     * The invariant that protects the data: the app clock is UTC and nothing
     * may move it. The previous implementation called date_default_timezone_set()
     * per tenant, which leaked between tenants in a reused worker process and
     * stored local time with no offset recorded.
     */
    public function test_the_application_clock_stays_utc_whatever_the_company_sets(): void
    {
        $this->useTimezone('Asia/Kathmandu');

        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('UTC', date_default_timezone_get());
        $this->assertSame('UTC', now()->getTimezone()->getName());
    }

    public function test_display_shifts_a_stored_utc_timestamp_into_the_company_zone(): void
    {
        $this->useCalendar(DateSystem::AD);
        $this->useTimezone('Asia/Kathmandu');

        // Nepal is +05:45 — the 45-minute offset is the part naive
        // implementations get wrong.
        $this->assertSame('2026-07-20 16:55', DateSystem::displayDateTime('2026-07-20 11:10:00'));
    }

    /**
     * The case that decides whether an evening attendance punch lands on the
     * right day: 20:00 UTC is already tomorrow in Kathmandu.
     */
    public function test_an_evening_utc_timestamp_reports_the_next_day_in_kathmandu(): void
    {
        $this->useCalendar(DateSystem::AD);
        $this->useTimezone('Asia/Kathmandu');

        $this->assertSame('2026-07-21', DateSystem::display('2026-07-20 20:00:00'));

        // Same instant, UTC company: still the 20th.
        $this->useTimezone('UTC');
        $this->assertSame('2026-07-20', DateSystem::display('2026-07-20 20:00:00'));
    }

    public function test_timezone_and_calendar_compose(): void
    {
        $this->useCalendar(DateSystem::BS);
        $this->useTimezone('Asia/Kathmandu');

        // 20:00 UTC -> 2026-07-21 in Kathmandu -> BS 2083-04-05.
        $this->assertSame('2083-04-05', DateSystem::display('2026-07-20 20:00:00'));
    }

    public function test_an_invalid_timezone_falls_back_to_utc(): void
    {
        $this->useTimezone('Mars/Olympus_Mons');

        $this->assertSame('UTC', DateSystem::timezone());
    }

    public function test_it_defaults_to_the_english_calendar(): void
    {
        SystemSetting::query()->where('key', DateSystem::SETTING_KEY)->delete();
        SystemSetting::forgetCache();

        $this->assertSame(DateSystem::AD, DateSystem::current());
        $this->assertFalse(DateSystem::isNepali());
    }

    public function test_the_setting_switches_the_active_calendar(): void
    {
        $this->useCalendar(DateSystem::BS);

        $this->assertTrue(DateSystem::isNepali());
        $this->assertSame(DateSystem::BS, DateSystem::current());
    }

    public function test_an_unrecognised_value_falls_back_to_english(): void
    {
        // A stray value must not leave dates rendering in an undefined calendar.
        $this->useCalendar('klingon');

        $this->assertSame(DateSystem::AD, DateSystem::current());
    }

    public function test_display_follows_the_company_calendar(): void
    {
        $this->useCalendar(DateSystem::AD);
        $this->assertSame('2026-07-20', DateSystem::display('2026-07-20'));

        $this->useCalendar(DateSystem::BS);
        $this->assertSame('2083-04-04', DateSystem::display('2026-07-20'));
    }

    public function test_long_display_spells_the_nepali_month(): void
    {
        $this->useCalendar(DateSystem::BS);

        $this->assertSame('Shrawan 4, 2083', DateSystem::displayLong('2026-07-20'));
    }

    public function test_conversion_round_trips(): void
    {
        $this->assertSame('2083-04-04', DateSystem::toBs('2026-07-20'));
        $this->assertSame('2026-07-20', DateSystem::toAd('2083-04-04'));
    }

    /**
     * A report must not 500 because one row holds a date the converter cannot
     * handle, so conversion failures degrade to the A.D. value.
     */
    public function test_unconvertible_and_null_dates_degrade_gracefully(): void
    {
        $this->useCalendar(DateSystem::BS);

        $this->assertNull(DateSystem::toBs('not-a-date'));
        $this->assertNull(DateSystem::toAd('9999-99-99'));
        $this->assertSame('—', DateSystem::display(null));
        $this->assertSame('n/a', DateSystem::display(null, 'n/a'));
    }

    /**
     * The picker's month-length table must come from the same source the server
     * converts with; a mismatch would let a user pick a day that does not exist.
     */
    public function test_the_calendar_payload_matches_the_package_data(): void
    {
        $payload = DateSystem::calendarPayload();

        $this->assertSame(2000, $payload['minBsYear']);
        $this->assertGreaterThanOrEqual(2090, $payload['maxBsYear']);
        $this->assertCount(12, $payload['months']);
        $this->assertSame('Baisakh', $payload['months'][0]);

        // Every year must carry exactly 12 month lengths, or the JS grid breaks.
        foreach ($payload['monthDays'] as $year => $lengths) {
            $this->assertCount(12, $lengths, "BS year {$year} has the wrong month count");
        }

        // The anchor the browser counts days from must be a real correspondence.
        $this->assertSame($payload['refAd'], DateSystem::toAd($payload['minBsYear'] . '-01-01'));
    }

    /**
     * The component posts A.D. from a hidden field, which is what keeps every
     * controller, validation rule and query calendar-agnostic.
     */
    public function test_the_component_renders_bs_for_display_but_posts_ad(): void
    {
        $this->useCalendar(DateSystem::BS);

        $html = view('components.date-field', [
            'name' => 'holiday_date',
            'value' => '2026-07-20',
            'label' => 'Holiday Date',
        ])->render();

        $this->assertStringContainsString('value="2083-04-04"', $html, 'visible field should show B.S.');
        $this->assertStringContainsString('name="holiday_date" value="2026-07-20"', $html, 'submitted field should be A.D.');
        $this->assertStringContainsString('data-system="bs"', $html);
    }

    public function test_the_component_shows_ad_in_both_fields_for_an_english_company(): void
    {
        $this->useCalendar(DateSystem::AD);

        $html = view('components.date-field', [
            'name' => 'holiday_date',
            'value' => '2026-07-20',
        ])->render();

        $this->assertStringContainsString('name="holiday_date" value="2026-07-20"', $html);
        $this->assertStringContainsString('data-system="ad"', $html);
        $this->assertStringNotContainsString('2083-04-04', $html);
    }
}

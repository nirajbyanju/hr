<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Modules\Attendance\Services\AttendanceService;
use App\Modules\Attendance\Services\AttendanceSlackNotifier;
use Illuminate\Support\Facades\Http;
use Tests\TenantTestCase;

/**
 * Slack notifications for attendance check-in / check-out.
 *
 * The webhook URL is a per-tenant credential stored encrypted in Settings; the
 * notification is fired after the response so it can never delay or break a
 * punch, and bulk CSV imports are excluded so backfilling history does not
 * flood the channel.
 */
class AttendanceSlackNotificationTest extends TenantTestCase
{
    private const WEBHOOK = 'https://hooks.slack.com/services/T00000000/B00000000/abcdefghij';

    private function enableSlack(?string $webhook = self::WEBHOOK): void
    {
        SystemSetting::put(AttendanceSlackNotifier::ENABLED_KEY, '1', ['group_name' => 'slack']);

        if ($webhook !== null) {
            SystemSetting::put(AttendanceSlackNotifier::WEBHOOK_KEY, $webhook, [
                'group_name' => 'slack',
                'is_encrypted' => true,
            ]);
        }

        SystemSetting::forgetCache();
    }

    private function makeUserWithPermissions(array $permissionSlugs): User
    {
        $user = User::factory()->create(['account_status' => 'active']);
        $role = Role::query()->create([
            'name' => 'Role ' . $user->id,
            'slug' => 'role-' . $user->id,
        ]);

        foreach ($permissionSlugs as $slug) {
            $permission = Permission::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $slug, 'group_name' => 'test']
            );
            $role->permissions()->attach($permission->id);
        }

        $user->roles()->attach($role->id);

        return $user;
    }

    private function makeEmployee(string $first = 'Sita', string $last = 'Rai'): Employee
    {
        return Employee::query()->create([
            'employee_code' => 'EMP-' . fake()->unique()->numerify('#####'),
            'first_name' => $first,
            'last_name' => $last,
            'date_of_joining' => '2026-01-01',
        ]);
    }

    private function log(string $entryType, int $employeeId): AttendanceLog
    {
        $at = now();

        return AttendanceLog::query()->create([
            'employee_id' => $employeeId,
            'attendance_date' => $at->format('Y-m-d'),
            'check_in_at' => $entryType === 'checkin' ? $at : null,
            'check_out_at' => $entryType === 'checkout' ? $at : null,
            'worked_minutes' => 0,
            'status' => 'present',
            'source' => 'test-' . $entryType,
        ]);
    }

    // ---- The notifier itself ------------------------------------------------

    public function test_it_posts_a_check_in_message_to_the_webhook(): void
    {
        Http::fake();
        $this->enableSlack();
        $employee = $this->makeEmployee('Sita', 'Rai');

        app(AttendanceSlackNotifier::class)->send($this->log('checkin', $employee->id));

        Http::assertSent(function ($request): bool {
            return $request->url() === self::WEBHOOK
                && str_contains($request['text'], 'checked in')
                && str_contains($request['text'], 'Sita Rai');
        });
    }

    public function test_it_posts_a_check_out_message(): void
    {
        Http::fake();
        $this->enableSlack();
        $employee = $this->makeEmployee();

        app(AttendanceSlackNotifier::class)->send($this->log('checkout', $employee->id));

        Http::assertSent(fn ($request): bool => str_contains($request['text'], 'checked out'));
    }

    public function test_a_non_slack_url_is_never_posted_to(): void
    {
        Http::fake();
        // An attacker-controlled internal URL must be ignored, not requested.
        $this->enableSlack('https://169.254.169.254/latest/meta-data');
        $employee = $this->makeEmployee();

        app(AttendanceSlackNotifier::class)->send($this->log('checkin', $employee->id));

        Http::assertNothingSent();
    }

    // ---- Gating through addManualLog ---------------------------------------

    public function test_a_live_punch_notifies_when_enabled(): void
    {
        Http::fake();
        $this->enableSlack();
        $employee = $this->makeEmployee();

        app(AttendanceService::class)->addManualLog($employee->id, [
            'attendance_date' => now()->format('Y-m-d'),
            'entry_type' => 'checkin',
            'entry_time' => now()->format('h:i A'),
        ]);

        // The post is scheduled for after the response; run terminating callbacks.
        $this->app->terminate();

        Http::assertSent(fn ($request): bool => str_contains($request['text'], 'checked in'));
    }

    public function test_nothing_is_sent_when_disabled(): void
    {
        Http::fake();
        $this->enableSlack();
        SystemSetting::put(AttendanceSlackNotifier::ENABLED_KEY, '0', ['group_name' => 'slack']);
        SystemSetting::forgetCache();
        $employee = $this->makeEmployee();

        app(AttendanceService::class)->addManualLog($employee->id, [
            'attendance_date' => now()->format('Y-m-d'),
            'entry_type' => 'checkin',
            'entry_time' => now()->format('h:i A'),
        ]);
        $this->app->terminate();

        Http::assertNothingSent();
    }

    public function test_bulk_import_does_not_notify(): void
    {
        Http::fake();
        $this->enableSlack();
        $employee = $this->makeEmployee();

        // notify: false is what the CSV import passes.
        app(AttendanceService::class)->addManualLog($employee->id, [
            'attendance_date' => now()->format('Y-m-d'),
            'entry_type' => 'checkin',
            'entry_time' => now()->format('h:i A'),
        ], null, 'manual', notify: false);
        $this->app->terminate();

        Http::assertNothingSent();
    }

    // ---- Settings storage ---------------------------------------------------

    public function test_saving_settings_stores_the_flag_and_encrypts_the_webhook(): void
    {
        $admin = $this->makeUserWithPermissions(['settings.update']);

        $this->actingAs($admin)->put(route('settings.update'), [
            'app_name' => 'Acme HR',
            'company_name' => 'Acme',
            'date_format' => 'Y-m-d',
            'date_system' => 'ad',
            'time_zone' => 'UTC',
            'mail_mailer' => 'smtp',
            'slack_notifications_enabled' => '1',
            'slack_webhook_url' => self::WEBHOOK,
        ])->assertSessionHasNoErrors();

        $this->assertSame('1', SystemSetting::getValue(AttendanceSlackNotifier::ENABLED_KEY));

        // Stored encrypted (raw column is not the plaintext) but reads back decrypted.
        $row = SystemSetting::query()->where('key', AttendanceSlackNotifier::WEBHOOK_KEY)->firstOrFail();
        $this->assertTrue((bool) $row->is_encrypted);
        $this->assertNotSame(self::WEBHOOK, $row->value);
        SystemSetting::forgetCache();
        $this->assertSame(self::WEBHOOK, SystemSetting::getValue(AttendanceSlackNotifier::WEBHOOK_KEY));
    }

    public function test_a_blank_webhook_on_save_keeps_the_current_one(): void
    {
        $admin = $this->makeUserWithPermissions(['settings.update']);
        $this->enableSlack();

        $this->actingAs($admin)->put(route('settings.update'), [
            'app_name' => 'Acme HR',
            'company_name' => 'Acme',
            'date_format' => 'Y-m-d',
            'date_system' => 'ad',
            'time_zone' => 'UTC',
            'mail_mailer' => 'smtp',
            'slack_notifications_enabled' => '1',
            'slack_webhook_url' => '',
        ])->assertSessionHasNoErrors();

        SystemSetting::forgetCache();
        $this->assertSame(self::WEBHOOK, SystemSetting::getValue(AttendanceSlackNotifier::WEBHOOK_KEY));
    }

    public function test_a_non_slack_webhook_is_rejected_by_validation(): void
    {
        $admin = $this->makeUserWithPermissions(['settings.update']);

        $this->actingAs($admin)->put(route('settings.update'), [
            'app_name' => 'Acme HR',
            'company_name' => 'Acme',
            'date_format' => 'Y-m-d',
            'date_system' => 'ad',
            'time_zone' => 'UTC',
            'mail_mailer' => 'smtp',
            'slack_notifications_enabled' => '1',
            'slack_webhook_url' => 'https://evil.example.com/hook',
        ])->assertSessionHasErrors('slack_webhook_url');
    }
}

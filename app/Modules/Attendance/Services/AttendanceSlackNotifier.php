<?php

namespace App\Modules\Attendance\Services;

use App\Models\AttendanceLog;
use App\Models\SystemSetting;
use App\Support\DateSystem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Posts an attendance check-in/check-out to a Slack channel via an Incoming
 * Webhook configured per tenant in Settings.
 *
 * Two rules shape it:
 *   1. It never delays or breaks a check-in. The post is scheduled to run after
 *      the HTTP response is sent (app terminating callback), and any failure is
 *      logged and swallowed — Slack being slow or down is not the punch's problem.
 *   2. The webhook URL is treated as a credential: stored encrypted, and only
 *      ever posted to when it is a real Slack hooks URL (an SSRF guard, since the
 *      value is admin-supplied and we make a server-side request to it).
 */
class AttendanceSlackNotifier
{
    /** Setting keys, shared with the Settings module so there is one source. */
    public const ENABLED_KEY = 'slack_notifications_enabled';

    public const WEBHOOK_KEY = 'slack_webhook_url';

    /** A webhook is only honoured if it points at Slack's own endpoint. */
    private const WEBHOOK_PREFIX = 'https://hooks.slack.com/';

    public function enabled(): bool
    {
        return (bool) SystemSetting::getValue(self::ENABLED_KEY, false)
            && $this->webhookUrl() !== null;
    }

    /**
     * Schedule the Slack post for after the response is flushed, so the user's
     * check-in returns immediately regardless of Slack's latency. Runs in the
     * same request process, so the tenant is still initialised.
     */
    public function queueAfterResponse(AttendanceLog $log): void
    {
        if (! $this->enabled()) {
            return;
        }

        // Load the name now, while the tenant connection is certainly active.
        $log->loadMissing('employee');

        app()->terminating(function () use ($log): void {
            $this->send($log);
        });
    }

    /** Post immediately. Safe to call directly (e.g. from tests). */
    public function send(AttendanceLog $log): void
    {
        $url = $this->webhookUrl();

        if ($url === null) {
            return;
        }

        try {
            Http::timeout(5)->post($url, ['text' => $this->message($log)]);
        } catch (\Throwable $exception) {
            Log::warning('Attendance Slack notification failed: ' . $exception->getMessage());
        }
    }

    /** The configured webhook, or null if it is missing or not a Slack URL. */
    private function webhookUrl(): ?string
    {
        $url = SystemSetting::getValue(self::WEBHOOK_KEY);

        return is_string($url) && str_starts_with($url, self::WEBHOOK_PREFIX) ? $url : null;
    }

    private function message(AttendanceLog $log): string
    {
        $isCheckout = $log->check_out_at !== null;
        $moment = $isCheckout ? $log->check_out_at : $log->check_in_at;

        $name = $log->employee?->fullName();
        $name = ($name === null || $name === '') ? ('Employee #' . $log->employee_id) : $name;

        // Timestamps are stored UTC; show them in the company's own zone.
        $zone = (string) SystemSetting::getValue(DateSystem::TIMEZONE_KEY, config('app.timezone'));
        $time = $moment ? $moment->copy()->timezone($zone)->format('g:i A') : '';
        $date = optional($log->attendance_date)->format('Y-m-d');

        return sprintf(
            '%s *%s* %s at %s (%s)',
            $isCheckout ? '🔴' : '🟢',
            $name,
            $isCheckout ? 'checked out' : 'checked in',
            $time,
            $date
        );
    }
}

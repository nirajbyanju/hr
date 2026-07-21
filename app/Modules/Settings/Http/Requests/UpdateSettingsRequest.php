<?php

namespace App\Modules\Settings\Http\Requests;

use App\Support\DateSystem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('weekend_days')) {
            $this->merge(['weekend_days' => []]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'app_name' => ['required', 'string', 'max:150'],
            'company_name' => ['required', 'string', 'max:180'],
            'company_email' => ['nullable', 'email', 'max:180'],
            'company_phone' => ['nullable', 'string', 'max:60'],
            'company_address' => ['nullable', 'string', 'max:1000'],
            'primary_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'currency_prefix' => ['nullable', 'string', 'max:20'],
            'employee_code_prefix' => ['nullable', 'string', 'max:30'],
            'invoice_prefix' => ['nullable', 'string', 'max:30'],
            'date_format' => ['required', 'string', 'max:40'],
            'date_system' => ['required', 'string', Rule::in([DateSystem::AD, DateSystem::BS])],
            'time_zone' => ['required', 'timezone'],
            'weekend_days' => ['array'],
            'weekend_days.*' => ['string', Rule::in(['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'])],

            // Work window used by the Attendance Records grid to derive
            // late / early / overtime / half-day.
            'work_start_time' => ['nullable', 'date_format:H:i'],
            'work_end_time' => ['nullable', 'date_format:H:i'],
            'standard_work_hours' => ['nullable', 'numeric', 'min:1', 'max:24'],
            'half_day_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'late_grace_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],

            'mail_mailer' => ['required', 'string', Rule::in(['smtp'])],
            'mail_host' => ['nullable', 'string', 'max:255'],
            'mail_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_encryption' => ['nullable', 'string', Rule::in(['tls', 'ssl', 'starttls'])],
            'mail_from_address' => ['nullable', 'email', 'max:180'],
            'mail_from_name' => ['nullable', 'string', 'max:180'],

            // Slack attendance notifications. The webhook is a credential, so it
            // is only accepted as a real Slack Incoming Webhook URL — this also
            // guards the server-side POST against being pointed at an internal
            // host (SSRF). Blank on save keeps the stored value (see the service).
            'slack_notifications_enabled' => ['nullable', 'boolean'],
            'slack_webhook_url' => ['nullable', 'string', 'url', 'max:255', 'starts_with:https://hooks.slack.com/'],

            'company_logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,svg,webp', 'max:4096'],
            'company_favicon' => ['nullable', 'file', 'mimes:png,ico,jpg,jpeg,svg,webp', 'max:2048'],
        ];
    }
}

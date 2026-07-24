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

            // Which attendance methods employees may use. At least one has to
            // stay on — see withValidator() — otherwise the Attendance page
            // would offer no way to mark attendance at all.
            'attendance_method_system' => ['nullable', 'boolean'],
            'attendance_method_qr' => ['nullable', 'boolean'],

            // Where System-Based Attendance may be marked from. Validated
            // loosely here and cross-checked in withValidator(), which is where
            // "switched on but not configured" is caught.
            'attendance_ip_restriction_enabled' => ['nullable', 'boolean'],
            'attendance_allowed_ips' => ['nullable', 'string', 'max:2000'],
            'attendance_geofence_enabled' => ['nullable', 'boolean'],
            'attendance_geofence_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'attendance_geofence_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'attendance_geofence_radius' => ['nullable', 'integer', 'min:10', 'max:100000'],

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

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator): void {
            // Only judge a payload that actually carries the attendance method
            // fields. The settings form always sends both (a hidden 0 sits behind
            // each checkbox); a save posted for some other section says nothing
            // about attendance methods and must not be failed over them.
            if (! $this->has('attendance_method_system') && ! $this->has('attendance_method_qr')) {
                return;
            }

            if (! $this->boolean('attendance_method_system') && ! $this->boolean('attendance_method_qr')) {
                $validator->errors()->add(
                    'attendance_method_system',
                    __('Enable at least one attendance method.')
                );
            }

            // A geofence with no centre would reject every clock-in, so both
            // coordinates are required once it is switched on.
            if ($this->boolean('attendance_geofence_enabled')) {
                foreach ([
                    'attendance_geofence_latitude' => __('Latitude is required to restrict attendance by location.'),
                    'attendance_geofence_longitude' => __('Longitude is required to restrict attendance by location.'),
                ] as $field => $message) {
                    if (! is_numeric($this->input($field))) {
                        $validator->errors()->add($field, $message);
                    }
                }
            }

            // Same reasoning for the IP allowlist: catch a malformed entry here
            // rather than silently never matching it.
            if ($this->boolean('attendance_ip_restriction_enabled')) {
                foreach (preg_split('/[\s,]+/', (string) $this->input('attendance_allowed_ips', '')) ?: [] as $entry) {
                    $entry = trim($entry);
                    if ($entry === '' || $this->isValidIpEntry($entry)) {
                        continue;
                    }

                    $validator->errors()->add(
                        'attendance_allowed_ips',
                        __(':entry is not a valid IP address or CIDR range.', ['entry' => $entry])
                    );
                }
            }
        });
    }

    private function isValidIpEntry(string $entry): bool
    {
        if (! str_contains($entry, '/')) {
            return filter_var($entry, FILTER_VALIDATE_IP) !== false;
        }

        [$subnet, $bits] = explode('/', $entry, 2);

        return filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
            && is_numeric($bits)
            && (int) $bits >= 0
            && (int) $bits <= 32;
    }
}

@extends('layouts.backend')

@section('content')
<div class="wrapper-page">
    <div class="page-title">
        <h1><i class="icon-settings"></i> {{ __('System Configuration') }}</h1>
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            <div>
                <div class="content_wrapper content-padded">
                    <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <h5 class="table_banner_title mb-3">{{ __('General') }}</h5>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label>{{ __('Application Name') }}</label>
                                <input type="text" class="form-control" name="app_name" value="{{ old('app_name', $settings['app_name'] ?? config('app.name')) }}" required>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>{{ __('Company Name') }}</label>
                                <input type="text" class="form-control" name="company_name" value="{{ old('company_name', $settings['company_name'] ?? '') }}" required>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>{{ __('Company Email') }}</label>
                                <input type="email" class="form-control" name="company_email" value="{{ old('company_email', $settings['company_email'] ?? '') }}">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>{{ __('Company Phone') }}</label>
                                <input type="text" class="form-control" name="company_phone" value="{{ old('company_phone', $settings['company_phone'] ?? '') }}">
                            </div>
                            <div class="col-md-12 form-group">
                                <label>{{ __('Company Address') }}</label>
                                <textarea class="form-control" rows="2" name="company_address">{{ old('company_address', $settings['company_address'] ?? '') }}</textarea>
                            </div>
                        </div>

                        <hr>
                        <h5 class="table_banner_title mb-3">{{ __('Branding') }}</h5>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <div class="brand-upload-box">
                                    <span class="label">{{ __('Logo') }}</span>
                                    <div class="brand-picker-row">
                                        <input id="company_logo" type="file" class="brand-file-input" name="company_logo" accept=".png,.jpg,.jpeg,.svg,.webp,image/*">
                                        <label for="company_logo" class="btn btn-custom mb-0">{{ __('Choose Logo') }}</label>
                                        <span id="company_logo_name" class="brand-file-name">{{ __('No file selected') }}</span>
                                    </div>
                                    @if(!empty($settings['company_logo']))
                                        <div class="mt-2"><img src="{{ asset($settings['company_logo']) }}" alt="Logo" class="settings-logo-preview"></div>
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-6 form-group">
                                <div class="brand-upload-box">
                                    <span class="label">{{ __('Favicon') }}</span>
                                    <div class="brand-picker-row">
                                        <input id="company_favicon" type="file" class="brand-file-input" name="company_favicon" accept=".ico,.png,.jpg,.jpeg,.svg,.webp,image/*">
                                        <label for="company_favicon" class="btn btn-custom mb-0">{{ __('Choose Favicon') }}</label>
                                        <span id="company_favicon_name" class="brand-file-name">{{ __('No file selected') }}</span>
                                    </div>
                                    @if(!empty($settings['company_favicon']))
                                        <div class="mt-2"><img src="{{ asset($settings['company_favicon']) }}" alt="Favicon" class="settings-favicon-preview"></div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <hr>
                        <h5 class="table_banner_title mb-3">{{ __('Appearance') }}</h5>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label>{{ __('Primary Color') }}</label>
                                <div class="color-field-row">
                                    <input type="color" class="color-swatch-input" id="primary_color_picker" value="{{ old('primary_color', $settings['primary_color'] ?? '#0f8f8c') }}">
                                    <input type="text" class="form-control color-hex-input" id="primary_color" name="primary_color" value="{{ old('primary_color', $settings['primary_color'] ?? '#0f8f8c') }}" maxlength="7" pattern="^#[0-9A-Fa-f]{6}$">
                                </div>
                                <small class="text-muted">{{ __('Buttons, links and highlighted actions.') }}</small>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>{{ __('Secondary Color') }}</label>
                                <div class="color-field-row">
                                    <input type="color" class="color-swatch-input" id="secondary_color_picker" value="{{ old('secondary_color', $settings['secondary_color'] ?? '#25364d') }}">
                                    <input type="text" class="form-control color-hex-input" id="secondary_color" name="secondary_color" value="{{ old('secondary_color', $settings['secondary_color'] ?? '#25364d') }}" maxlength="7" pattern="^#[0-9A-Fa-f]{6}$">
                                </div>
                                <small class="text-muted">{{ __('Sidebar and dark surface tone.') }}</small>
                            </div>
                        </div>

                        <hr>
                        <h5 class="table_banner_title mb-3">{{ __('Localization') }}</h5>
                        @php($weekendDaysSelected = old('weekend_days', array_values(array_filter(explode(',', (string) ($settings['weekend_days'] ?? 'sat,sun'))))))
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label>{{ __('Currency Prefix') }}</label>
                                <input type="text" class="form-control" name="currency_prefix" value="{{ old('currency_prefix', $settings['currency_prefix'] ?? '৳') }}">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>{{ __('Employee Code Prefix') }}</label>
                                <input type="text" class="form-control" name="employee_code_prefix" value="{{ old('employee_code_prefix', $settings['employee_code_prefix'] ?? 'EMP') }}">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>{{ __('Invoice Prefix') }}</label>
                                <input type="text" class="form-control" name="invoice_prefix" value="{{ old('invoice_prefix', $settings['invoice_prefix'] ?? 'INV') }}">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>{{ __('Date Format') }}</label>
                                <input type="text" class="form-control" name="date_format" value="{{ old('date_format', $settings['date_format'] ?? 'Y-m-d') }}" required>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>{{ __('Calendar System') }}</label>
                                @php($dateSystem = old('date_system', $settings['date_system'] ?? \App\Support\DateSystem::AD))
                                <select class="form-control" name="date_system" required>
                                    <option value="{{ \App\Support\DateSystem::AD }}" {{ $dateSystem === \App\Support\DateSystem::AD ? 'selected' : '' }}>
                                        {{ __('English (Gregorian / A.D.)') }}
                                    </option>
                                    <option value="{{ \App\Support\DateSystem::BS }}" {{ $dateSystem === \App\Support\DateSystem::BS ? 'selected' : '' }}>
                                        {{ __('Nepali (Bikram Sambat / B.S.)') }}
                                    </option>
                                </select>
                                <small class="form-text text-muted">
                                    {{ __('Applies to every date picker and date shown across the system. Dates are always stored internally as A.D., so switching back and forth never changes your data.') }}
                                </small>
                                @error('date_system')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6 form-group">
                                <label>{{ __('Time Zone') }}</label>
                                @php($tz = old('time_zone', $settings['time_zone'] ?? config('app.timezone')))
                                <select class="form-control" name="time_zone" required>
                                    @foreach($timezones as $zone)
                                        <option value="{{ $zone }}" {{ $tz === $zone ? 'selected' : '' }}>{{ $zone }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <hr>
                            <h5 class="table_banner_title mb-3">{{ __('Weekend Days') }}</h5>
                            <div class="col-md-12 form-group">
                                <div class="d-flex flex-wrap">
                                    @foreach(['sun' => 'Sunday', 'mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday'] as $dayKey => $dayLabel)
                                        @php($checkboxId = 'weekend_day_'.$dayKey)
                                        <div class="me-3">
                                            <input id="{{ $checkboxId }}" type="checkbox" name="weekend_days[]" value="{{ $dayKey }}" {{ in_array($dayKey, $weekendDaysSelected, true) ? 'checked' : '' }}>
                                            <label for="{{ $checkboxId }}">{{ $dayLabel }}</label>
                                        </div>
                                    @endforeach
                                </div>
                                <small class="text-muted">{{ __('Selected days will be highlighted as weekend in Holidays calendar.') }}</small>
                            </div>
                        </div>

                        <hr>
                        <h5 class="table_banner_title mb-3">{{ __('Work Hours') }}</h5>
                        <p class="text-muted mb-3"><small>{{ __('Used by the Attendance Records grid to flag late arrivals, early departures, overtime and half-days.') }}</small></p>
                        <div class="row">
                            <div class="col-md-3 form-group">
                                <label>{{ __('Work start time') }}</label>
                                <input type="time" class="form-control" name="work_start_time" value="{{ old('work_start_time', $settings['work_start_time'] ?? '09:00') }}">
                            </div>
                            <div class="col-md-3 form-group">
                                <label>{{ __('Work end time') }}</label>
                                <input type="time" class="form-control" name="work_end_time" value="{{ old('work_end_time', $settings['work_end_time'] ?? '17:00') }}">
                            </div>
                            <div class="col-md-2 form-group">
                                <label>{{ __('Standard hours') }}</label>
                                <input type="number" step="0.5" min="1" max="24" class="form-control" name="standard_work_hours" value="{{ old('standard_work_hours', $settings['standard_work_hours'] ?? '8') }}">
                            </div>
                            <div class="col-md-2 form-group">
                                <label>{{ __('Half-day under (hrs)') }}</label>
                                <input type="number" step="0.5" min="0" max="24" class="form-control" name="half_day_hours" value="{{ old('half_day_hours', $settings['half_day_hours'] ?? '4') }}">
                            </div>
                            <div class="col-md-2 form-group">
                                <label>{{ __('Late grace (min)') }}</label>
                                <input type="number" min="0" max="240" class="form-control" name="late_grace_minutes" value="{{ old('late_grace_minutes', $settings['late_grace_minutes'] ?? '15') }}">
                            </div>
                        </div>

                        <hr>
                        <h5 class="table_banner_title mb-3">{{ __('SMTP Configuration') }}</h5>
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label>{{ __('Mailer') }}</label>
                                <input type="text" class="form-control" value="SMTP" readonly>
                                <input type="hidden" name="mail_mailer" value="smtp">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>{{ __('Mail Host') }}</label>
                                <input type="text" class="form-control" name="mail_host" value="{{ old('mail_host', $settings['mail_host'] ?? config('mail.mailers.smtp.host')) }}">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>{{ __('Mail Port') }}</label>
                                <input type="number" class="form-control" name="mail_port" value="{{ old('mail_port', $settings['mail_port'] ?? config('mail.mailers.smtp.port')) }}">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>{{ __('Mail Username') }}</label>
                                <input type="text" class="form-control" name="mail_username" value="{{ old('mail_username', $settings['mail_username'] ?? config('mail.mailers.smtp.username')) }}">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>{{ __('Mail Password') }}</label>
                                <input type="password" class="form-control" name="mail_password" placeholder="{{ __('Leave blank to keep current password') }}">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>{{ __('Encryption') }}</label>
                                <select class="form-control" name="mail_encryption">
                                    @php($enc = old('mail_encryption', $settings['mail_encryption'] ?? config('mail.mailers.smtp.encryption')))
                                    <option value="">{{ __('None') }}</option>
                                    @foreach(['tls','ssl','starttls'] as $opt)
                                        <option value="{{ $opt }}" {{ $enc === $opt ? 'selected' : '' }}>{{ strtoupper($opt) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>{{ __('From Address') }}</label>
                                <input type="email" class="form-control" name="mail_from_address" value="{{ old('mail_from_address', $settings['mail_from_address'] ?? config('mail.from.address')) }}">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>{{ __('From Name') }}</label>
                                <input type="text" class="form-control" name="mail_from_name" value="{{ old('mail_from_name', $settings['mail_from_name'] ?? config('mail.from.name')) }}">
                            </div>
                        </div>

                        <hr>
                        <h5 class="table_banner_title mb-1">{{ __('Slack Notifications') }}</h5>
                        <p class="text-muted mb-3" style="font-size:13px;">
                            {{ __('Post a message to a Slack channel whenever an employee checks in or out.') }}
                            {{ __('Create an Incoming Webhook in Slack pointed at your attendance channel, then paste its URL below.') }}
                        </p>
                        @php($slackEnabled = (bool) old('slack_notifications_enabled', ($settings['slack_notifications_enabled'] ?? '0') === '1'))
                        @php($slackConfigured = ! empty($settings['slack_webhook_url']))
                        <div class="row">
                            <div class="col-md-12 form-group">
                                <label class="d-flex align-items-center" style="gap:8px;cursor:pointer;">
                                    <input type="checkbox" name="slack_notifications_enabled" value="1" {{ $slackEnabled ? 'checked' : '' }}>
                                    <span>{{ __('Enable attendance check-in / check-out notifications') }}</span>
                                </label>
                            </div>
                            <div class="col-md-8 form-group">
                                <label>{{ __('Slack Webhook URL') }}</label>
                                <input type="password" class="form-control" name="slack_webhook_url" autocomplete="off"
                                       placeholder="{{ $slackConfigured ? __('Leave blank to keep current webhook') : 'https://hooks.slack.com/services/…' }}">
                                <small class="text-muted">
                                    @if($slackConfigured)
                                        <i class="icon-check"></i> {{ __('A webhook is currently configured. Enter a new URL to replace it.') }}
                                    @else
                                        {{ __('No webhook configured yet. Must be a https://hooks.slack.com/ URL.') }}
                                    @endif
                                </small>
                                @error('slack_webhook_url')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="mt-3">
                            <button type="submit" class="btn btn-custom">
                                <i class="icon-check"></i> {{ __('Save Settings') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        function bindFileName(inputId, outputId) {
            var input = document.getElementById(inputId);
            var output = document.getElementById(outputId);
            if (!input || !output) {
                return;
            }

            input.addEventListener('change', function () {
                if (input.files && input.files.length > 0) {
                    output.textContent = input.files[0].name;
                } else {
                    output.textContent = @json(__('No file selected'));
                }
            });
        }

        bindFileName('company_logo', 'company_logo_name');
        bindFileName('company_favicon', 'company_favicon_name');

        var HEX_RE = /^#[0-9A-Fa-f]{6}$/;

        function bindColorField(pickerId, hexId, cssVar) {
            var picker = document.getElementById(pickerId);
            var hex = document.getElementById(hexId);
            if (!picker || !hex) {
                return;
            }

            picker.addEventListener('input', function () {
                hex.value = picker.value;
                document.documentElement.style.setProperty(cssVar, picker.value);
            });

            hex.addEventListener('input', function () {
                var value = hex.value.trim();
                if (HEX_RE.test(value)) {
                    picker.value = value;
                    document.documentElement.style.setProperty(cssVar, value);
                }
            });
        }

        bindColorField('primary_color_picker', 'primary_color', '--hr-accent');
        bindColorField('secondary_color_picker', 'secondary_color', '--hr-primary');
    })();
</script>
@endpush

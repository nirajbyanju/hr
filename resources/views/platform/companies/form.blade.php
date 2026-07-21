@extends('platform.layout')

@section('title', $mode === 'create' ? 'Add company' : 'Edit company')

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $mode === 'create' ? 'Add company' : 'Edit ' . $company->name }}</h1>
            <p>{{ $mode === 'create' ? 'Create a tenant and its first admin in one step.' : 'Update company details.' }}</p>
        </div>
        <a href="{{ route('platform.dashboard') }}" class="btn btn-ghost">← Back</a>
    </div>

    @php($adminUser = $adminUser ?? null)
    <div class="card form-card">
        <form method="POST" action="{{ $mode === 'create' ? route('platform.companies.store') : route('platform.companies.update', $company) }}">
            @csrf
            @if($mode === 'edit') @method('PUT') @endif

            <div class="form-section">
                <div class="section-title">
                    <h2>Company profile</h2>
                    <p>Core identity and tenant address.</p>
                </div>

                <label class="field">
                    <span class="lab">Company name</span>
                    <input class="input" type="text" name="name" value="{{ old('name', $company->name) }}" required autofocus>
                    @error('name')<div class="err">{{ $message }}</div>@enderror
                </label>

                <label class="field">
                    <span class="lab">Company domain</span>
                    <input class="input" type="text" name="domain" id="company_domain" value="{{ old('domain', $company->domain) }}"
                           placeholder="ktm.com" autocapitalize="none" spellcheck="false" required>
                    <div class="help">
                        Staff sign in with their email at this domain — someone@ktm.com signs in to this company.
                        @if($mode === 'edit')
                            <strong>Changing it will lock out anyone whose email uses the old domain.</strong>
                        @endif
                    </div>
                    @error('domain')<div class="err">{{ $message }}</div>@enderror
                </label>

                @if($mode === 'edit')
                    <label class="field">
                        <span class="lab">Status</span>
                        @php($status = old('status', $company->status))
                        <select class="input" name="status">
                            <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Active</option>
                            <option value="suspended" {{ $status === 'suspended' ? 'selected' : '' }}>Suspended (block login)</option>
                        </select>
                        <div class="help">Suspending signs out anyone currently logged in to this company.</div>
                        @error('status')<div class="err">{{ $message }}</div>@enderror
                    </label>
                @endif
            </div>

            <div class="form-section">
                <div class="section-title">
                    <h2>Subscription</h2>
                    <p>Use an expiry date to automatically block tenant access after the plan ends, and a seat limit to cap how many accounts the company can create.</p>
                </div>

                <div class="grid-2">
                    @include('platform.partials.date-field', [
                        'name' => 'starts_on',
                        'label' => 'Start date',
                        'value' => old('starts_on', optional($company->starts_on)->format('Y-m-d')),
                        'placeholder' => 'No start date',
                    ])

                    @include('platform.partials.date-field', [
                        'name' => 'expires_on',
                        'label' => 'Expiry date',
                        'value' => old('expires_on', optional($company->expires_on)->format('Y-m-d')),
                        'placeholder' => 'No expiry',
                        'help' => 'Leave blank for no expiry.',
                        'minFrom' => 'starts_on',
                        'presets' => [
                            ['label' => '3 months', 'months' => 3],
                            ['label' => '6 months', 'months' => 6],
                            ['label' => '1 year', 'months' => 12],
                            ['label' => '2 years', 'months' => 24],
                        ],
                    ])
                </div>

                <div class="help" id="term_summary" hidden></div>

                <label class="field">
                    <span class="lab">User account limit</span>
                    <input class="input" type="number" name="user_limit" min="1" max="100000" step="1"
                           value="{{ old('user_limit', $company->user_limit) }}" placeholder="Unlimited">
                    <div class="help">
                        The most login accounts this company may create, including the admin below.
                        Leave blank for unlimited.
                        @if($mode === 'edit')
                            Currently using <strong>{{ $company->users_count ?? '—' }}</strong> account(s).
                            Lowering the limit never removes existing accounts — it only blocks new ones.
                        @endif
                    </div>
                    @error('user_limit')<div class="err">{{ $message }}</div>@enderror
                </label>
            </div>

            <div class="form-section">
                <div class="section-title">
                    <h2>Admin login</h2>
                    <p>{{ $mode === 'create' ? 'Create the first company administrator.' : 'Change the company admin email or set a new password.' }}</p>
                </div>

                @php($adminEmail = (string) old('admin_email', $adminUser?->email))
                @php($adminLocal = \Illuminate\Support\Str::before($adminEmail, '@'))
                <label class="field">
                    <span class="lab">Admin email</span>
                    <div class="prefixed">
                        <input class="input" type="text" name="admin_email" id="admin_email" value="{{ $adminLocal }}"
                               placeholder="admin" autocapitalize="none" spellcheck="false" {{ $mode === 'create' ? 'required' : '' }}>
                        <span class="suffix" id="admin_email_suffix" data-fallback="ktm.com">{{ '@' . (old('domain', $company->domain) ?: 'ktm.com') }}</span>
                    </div>
                    <div class="help">The company domain above is added automatically — type the name before the @ only.</div>
                    @if($mode === 'edit' && $adminUser === null)
                        <div class="help">No admin user was found for this company.</div>
                    @endif
                    @error('admin_email')<div class="err">{{ $message }}</div>@enderror
                </label>

                <div class="grid-2">
                    <label class="field">
                        <span class="lab">{{ $mode === 'create' ? 'Admin password' : 'New admin password' }}</span>
                        <input class="input" type="password" name="admin_password" {{ $mode === 'create' ? 'required' : '' }}>
                        @if($mode === 'edit')<div class="help">Leave blank to keep the current password.</div>@endif
                        @error('admin_password')<div class="err">{{ $message }}</div>@enderror
                    </label>

                    <label class="field">
                        <span class="lab">Confirm password</span>
                        <input class="input" type="password" name="admin_password_confirmation" {{ $mode === 'create' ? 'required' : '' }}>
                    </label>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">{{ $mode === 'create' ? 'Create company' : 'Save changes' }}</button>
                <a href="{{ route('platform.dashboard') }}" class="btn btn-ghost">Cancel</a>
            </div>
        </form>
    </div>

    <script>
        // Keep the @domain shown beside the admin email in step with the domain
        // field. The server composes the address either way; this is only so the
        // admin can see what they are about to create.
        (function () {
            var domain = document.getElementById('company_domain');
            var suffix = document.getElementById('admin_email_suffix');

            if (! domain || ! suffix) {
                return;
            }

            var sync = function () {
                var value = domain.value.trim().toLowerCase();
                suffix.textContent = '@' + (value || suffix.dataset.fallback);
            };

            domain.addEventListener('input', sync);
            sync();
        })();

        // Spell out the subscription term, so a mistyped year is obvious before
        // saving rather than after. Validation of the pair stays server-side.
        (function () {
            var starts = document.querySelector('[name="starts_on"]');
            var expires = document.querySelector('[name="expires_on"]');
            var summary = document.getElementById('term_summary');

            if (! starts || ! expires || ! summary) {
                return;
            }

            var parse = function (value) {
                var parts = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value || '');

                return parts ? new Date(+parts[1], +parts[2] - 1, +parts[3]) : null;
            };

            var sync = function () {
                var from = parse(starts.value);
                var to = parse(expires.value);

                if (! from || ! to) {
                    summary.hidden = true;

                    return;
                }

                if (to < from) {
                    summary.textContent = 'The expiry date is before the start date.';
                    summary.hidden = false;

                    return;
                }

                var days = Math.round((to - from) / 86400000);
                var months = (to.getFullYear() - from.getFullYear()) * 12 + (to.getMonth() - from.getMonth());

                if (to.getDate() < from.getDate()) {
                    months--;
                }

                summary.textContent = months >= 1
                    ? 'Term: ' + months + (months === 1 ? ' month' : ' months') + ' (' + days + ' days).'
                    : 'Term: ' + days + (days === 1 ? ' day' : ' days') + '.';
                summary.hidden = false;
            };

            starts.addEventListener('change', sync);
            expires.addEventListener('change', sync);
            sync();
        })();
    </script>
@endsection

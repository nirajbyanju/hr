@extends('platform.layout')

@section('title', $mode === 'create' ? 'Add company' : 'Edit company')

@section('content')
    @php($isDefault = $company->exists && $company->isDefault())
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
                    <input class="input" type="text" name="domain" value="{{ old('domain', $company->domain) }}"
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
                        <select class="input" name="status" {{ $isDefault ? 'disabled' : '' }}>
                            <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Active</option>
                            <option value="suspended" {{ $status === 'suspended' ? 'selected' : '' }}>Suspended (block login)</option>
                        </select>
                        @if($isDefault)<input type="hidden" name="status" value="active">@endif
                        @if($isDefault)<div class="help">The default company is always active and cannot be suspended.</div>@endif
                        @error('status')<div class="err">{{ $message }}</div>@enderror
                    </label>
                @endif
            </div>

            <div class="form-section">
                <div class="section-title">
                    <h2>Subscription dates</h2>
                    <p>Use an expiry date to automatically block tenant access after the plan ends.</p>
                </div>

                <div class="grid-2">
                    <label class="field">
                        <span class="lab">Start date</span>
                        <input class="input" type="date" name="starts_on" value="{{ old('starts_on', optional($company->starts_on)->format('Y-m-d')) }}">
                        @error('starts_on')<div class="err">{{ $message }}</div>@enderror
                    </label>

                    <label class="field">
                        <span class="lab">Expiry date</span>
                        <input class="input" type="date" name="expires_on" value="{{ old('expires_on', optional($company->expires_on)->format('Y-m-d')) }}">
                        <div class="help">Leave blank for no expiry.</div>
                        @error('expires_on')<div class="err">{{ $message }}</div>@enderror
                    </label>
                </div>
            </div>

            <div class="form-section">
                <div class="section-title">
                    <h2>Admin login</h2>
                    <p>{{ $mode === 'create' ? 'Create the first company administrator.' : 'Change the company admin email or set a new password.' }}</p>
                </div>

                <label class="field">
                    <span class="lab">Admin email</span>
                    <input class="input" type="email" name="admin_email" value="{{ old('admin_email', $adminUser?->email) }}" {{ $mode === 'create' ? 'required' : '' }}>
                    <div class="help">Must use the company domain above, e.g. admin@ktm.com.</div>
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
@endsection

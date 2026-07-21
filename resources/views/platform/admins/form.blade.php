@extends('platform.layout')

@section('title', $mode === 'create' ? 'Add admin' : 'Edit admin')

@section('content')
    @php($isSelf = $isSelf ?? false)

    <div class="page-head">
        <div>
            <h1>{{ $mode === 'create' ? 'Add platform admin' : 'Edit ' . $admin->name }}</h1>
            <p>{{ $mode === 'create'
                ? 'Create an account that can sign in to this console.'
                : 'Update this administrator’s details and access window.' }}</p>
        </div>
        <a href="{{ $mode === 'create' ? route('platform.admins.index') : route('platform.admins.show', $admin) }}" class="btn btn-ghost">← Back</a>
    </div>

    <div class="card form-card">
        <form method="POST" action="{{ $mode === 'create' ? route('platform.admins.store') : route('platform.admins.update', $admin) }}">
            @csrf
            @if($mode === 'edit') @method('PUT') @endif

            <div class="form-section">
                <div class="section-title">
                    <h2>Details</h2>
                    <p>The name and sign-in address for this administrator.</p>
                </div>

                <label class="field">
                    <span class="lab">Full name</span>
                    <input class="input" type="text" name="name" value="{{ old('name', $admin->name) }}" required autofocus>
                    @error('name')<div class="err">{{ $message }}</div>@enderror
                </label>

                <label class="field">
                    <span class="lab">Email</span>
                    <input class="input" type="email" name="email" value="{{ old('email', $admin->email) }}"
                           autocapitalize="none" spellcheck="false" required>
                    <div class="help">Used to sign in to the console. Any domain is fine — console accounts are not tied to a company.</div>
                    @error('email')<div class="err">{{ $message }}</div>@enderror
                </label>
            </div>

            @if($mode === 'create')
                <div class="form-section">
                    <div class="section-title">
                        <h2>Password</h2>
                        <p>The administrator can change this themselves once they sign in.</p>
                    </div>

                    <div class="grid-2">
                        <label class="field">
                            <span class="lab">Password</span>
                            <input class="input" type="password" name="password" required autocomplete="new-password">
                            @error('password')<div class="err">{{ $message }}</div>@enderror
                        </label>

                        <label class="field">
                            <span class="lab">Confirm password</span>
                            <input class="input" type="password" name="password_confirmation" required autocomplete="new-password">
                        </label>
                    </div>
                </div>
            @endif

            <div class="form-section">
                <div class="section-title">
                    <h2>Access</h2>
                    <p>Control whether this account can sign in, and for how long.</p>
                </div>

                @include('platform.partials.date-field', [
                    'name' => 'expires_on',
                    'label' => 'Expiry date',
                    'value' => old('expires_on', optional($admin->expires_on)->format('Y-m-d')),
                    'placeholder' => 'No expiry',
                    'help' => 'After this date the account can no longer sign in, and an open session is ended on the next request. Leave blank for no expiry.',
                    'presets' => [
                        ['label' => '3 months', 'months' => 3],
                        ['label' => '6 months', 'months' => 6],
                        ['label' => '1 year', 'months' => 12],
                    ],
                ])

                @if($isSelf)
                    <div class="note">
                        This is your own account, so it stays enabled — disabling it would sign you out with no way back in.
                    </div>
                @else
                    <label class="check">
                        <input type="checkbox" name="is_active" value="1"
                               {{ old('is_active', $mode === 'create' ? true : $admin->is_active) ? 'checked' : '' }}>
                        <span>
                            <b>Account is enabled</b>
                            <span class="help">Unchecking blocks sign-in immediately and ends any open session.</span>
                        </span>
                    </label>
                    @error('is_active')<div class="err">{{ $message }}</div>@enderror
                @endif
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">{{ $mode === 'create' ? 'Create admin' : 'Save changes' }}</button>
                <a href="{{ $mode === 'create' ? route('platform.admins.index') : route('platform.admins.show', $admin) }}" class="btn btn-ghost">Cancel</a>
            </div>
        </form>
    </div>
@endsection

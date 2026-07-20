@extends('platform.layout')

@section('title', $isSelf ? 'Change password' : 'Set password')

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $isSelf ? 'Change your password' : 'Set password for ' . $admin->name }}</h1>
            <p>
                {{ $isSelf
                    ? 'You will stay signed in on this device.'
                    : 'They will be signed out everywhere and must use the new password.' }}
            </p>
        </div>
        <a href="{{ route('platform.admins.show', $admin) }}" class="btn btn-ghost">← Back</a>
    </div>

    <div class="card form-card">
        <form method="POST" action="{{ route('platform.admins.password.update', $admin) }}">
            @csrf
            @method('PUT')

            <div class="form-section">
                <div class="card-head" style="padding:0 0 18px; border:none;">
                    <span class="avatar">{{ $admin->initials() }}</span>
                    <div>
                        <h2 style="font-size:16px;">{{ $admin->name }}</h2>
                        <p class="sub">{{ $admin->email }}</p>
                    </div>
                </div>

                @if($isSelf)
                    <label class="field">
                        <span class="lab">Current password</span>
                        <input class="input" type="password" name="current_password" required autocomplete="current-password" autofocus>
                        @error('current_password')<div class="err">{{ $message }}</div>@enderror
                    </label>
                @else
                    <div class="note" style="margin-bottom:16px;">
                        You are setting another administrator's password. They are not asked for their old one, and every
                        session they have open is ended.
                    </div>
                @endif

                <div class="grid-2">
                    <label class="field">
                        <span class="lab">New password</span>
                        <input class="input" type="password" name="password" required autocomplete="new-password"
                               {{ $isSelf ? '' : 'autofocus' }}>
                        @error('password')<div class="err">{{ $message }}</div>@enderror
                    </label>

                    <label class="field">
                        <span class="lab">Confirm new password</span>
                        <input class="input" type="password" name="password_confirmation" required autocomplete="new-password">
                    </label>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">{{ $isSelf ? 'Change password' : 'Set password' }}</button>
                <a href="{{ route('platform.admins.show', $admin) }}" class="btn btn-ghost">Cancel</a>
            </div>
        </form>
    </div>
@endsection

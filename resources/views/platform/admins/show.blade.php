@extends('platform.layout')

@section('title', $admin->name)

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $admin->name }}</h1>
            <p>Platform administrator account.</p>
        </div>
        <div class="row-actions">
            <a href="{{ route('platform.admins.index') }}" class="btn btn-ghost">← All admins</a>
            <a href="{{ route('platform.admins.edit', $admin) }}" class="btn btn-primary">Edit</a>
        </div>
    </div>

    <div class="split">
        <div class="card">
            <div class="card-head">
                <span class="avatar avatar-lg">{{ $admin->initials() }}</span>
                <div style="flex:1; min-width:0;">
                    <h2>{{ $admin->name }} @if($isSelf)<span class="pill pill-default">You</span>@endif</h2>
                    <p class="sub">{{ $admin->email }}</p>
                </div>
                @php($status = $admin->statusLabel())
                <span class="pill {{ $status === 'Active' ? 'pill-ok' : ($status === 'Expired' ? 'pill-expired' : 'pill-off') }}">{{ $status }}</span>
            </div>

            <div class="card-body">
                <dl style="margin:0;">
                    <div class="kv">
                        <dt>Can sign in</dt>
                        <dd>{{ $admin->isUsable() ? 'Yes' : 'No' }}
                            @unless($admin->isUsable())
                                <small>{{ $admin->inactiveReason() }}</small>
                            @endunless
                        </dd>
                    </div>
                    <div class="kv">
                        <dt>Expiry date</dt>
                        <dd>
                            @if($admin->expires_on === null)
                                No expiry
                            @else
                                {{ $admin->expires_on->format('M d, Y') }}
                                @php($days = $admin->daysUntilExpiry())
                                <small>
                                    @if($days < 0) Expired {{ abs($days) }} days ago
                                    @elseif($days === 0) Expires today
                                    @else {{ $days }} days left
                                    @endif
                                </small>
                            @endif
                        </dd>
                    </div>
                    <div class="kv">
                        <dt>Last sign-in</dt>
                        <dd>{{ $admin->last_login_at?->format('M d, Y H:i') ?? 'Never' }}
                            @if($admin->last_login_at)<small>{{ $admin->last_login_at->diffForHumans() }}</small>@endif
                        </dd>
                    </div>
                    <div class="kv">
                        <dt>Account created</dt>
                        <dd>{{ $admin->created_at?->format('M d, Y') ?? '—' }}</dd>
                    </div>
                    <div class="kv">
                        <dt>Last updated</dt>
                        <dd>{{ $admin->updated_at?->format('M d, Y H:i') ?? '—' }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2 style="font-size:16px;">Actions</h2></div>

            <div class="stack">
                <a href="{{ route('platform.admins.edit', $admin) }}" class="btn btn-ghost">✎ Edit details</a>

                <a href="{{ route('platform.admins.password.edit', $admin) }}" class="btn btn-ghost">
                    {{ $isSelf ? '⚿ Change my password' : '⚿ Set new password' }}
                </a>

                @if($isSelf)
                    <div class="note">You cannot disable or delete your own account.</div>
                @elseif($isLastUsable)
                    <div class="note">
                        This is the only administrator who can sign in. Add another before disabling or deleting it.
                    </div>
                @else
                    <form method="POST" action="{{ route('platform.admins.status', $admin) }}">
                        @csrf @method('PATCH')
                        <button type="submit" class="btn btn-ghost" style="width:100%; justify-content:flex-start;">
                            {{ $admin->is_active ? '⊘ Disable account' : '✓ Enable account' }}
                        </button>
                    </form>

                    <form method="POST" action="{{ route('platform.admins.destroy', $admin) }}"
                          onsubmit="return confirm('Delete {{ $admin->name }}? This cannot be undone.');">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-danger" style="width:100%; justify-content:flex-start;">
                            ✕ Delete admin
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
@endsection

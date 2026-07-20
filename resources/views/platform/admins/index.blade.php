@extends('platform.layout')

@section('title', 'Platform admins')

@section('content')
    <div class="page-head">
        <div>
            <h1>Platform admins</h1>
            <p>Accounts that can sign in to this console. Every admin can manage every other one.</p>
        </div>
        <a href="{{ route('platform.admins.create') }}" class="btn btn-primary">+ Add admin</a>
    </div>

    <div class="stats">
        <div class="stat"><div class="n">{{ $stats['total'] }}</div><div class="l">Admins</div></div>
        <div class="stat"><div class="n">{{ $stats['active'] }}</div><div class="l">Can sign in</div></div>
        <div class="stat"><div class="n">{{ $stats['disabled'] }}</div><div class="l">Disabled</div></div>
        <div class="stat"><div class="n">{{ $stats['expired'] }}</div><div class="l">Expired</div></div>
    </div>

    <div class="card">
        <form method="GET" action="{{ route('platform.admins.index') }}" class="filters">
            <div class="search">
                <span class="mag">⌕</span>
                <input class="input" type="search" name="q" value="{{ $filters['q'] }}"
                       placeholder="Search by name or email…" aria-label="Search admins">
            </div>

            <select class="input" name="status" aria-label="Filter by status">
                @php($statuses = ['' => 'All statuses', 'active' => 'Active', 'disabled' => 'Disabled', 'expired' => 'Expired', 'expiring' => 'Expiring in 30 days'])
                @foreach($statuses as $value => $label)
                    <option value="{{ $value }}" {{ $filters['status'] === $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>

            <button type="submit" class="btn btn-ghost">Apply</button>

            @if($filters['q'] !== '' || $filters['status'] !== '')
                <a href="{{ route('platform.admins.index') }}" class="btn btn-ghost btn-sm">Clear</a>
                <span class="filter-chip">{{ $admins->total() }} match{{ $admins->total() === 1 ? '' : 'es' }}</span>
            @endif
        </form>

        @if($admins->isEmpty())
            <div class="empty">
                <div class="ico">◉</div>
                @if($filters['q'] !== '' || $filters['status'] !== '')
                    <h3>No admins match those filters</h3>
                    <p>Try a different name, email or status.</p>
                    <a href="{{ route('platform.admins.index') }}" class="btn btn-ghost">Clear filters</a>
                @else
                    <h3>No platform admins yet</h3>
                    <p>Add an account so someone can sign in to this console.</p>
                    <a href="{{ route('platform.admins.create') }}" class="btn btn-primary">+ Add admin</a>
                @endif
            </div>
        @else
            <div class="tbl-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Admin</th>
                            <th>Status</th>
                            <th>Expiry</th>
                            <th>Last sign-in</th>
                            <th>Added</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($admins as $admin)
                            @php($isSelf = auth('central')->id() === $admin->id)
                            <tr>
                                <td>
                                    <div class="idcell">
                                        <span class="avatar">{{ $admin->initials() }}</span>
                                        <span class="who">
                                            <b>
                                                <a href="{{ route('platform.admins.show', $admin) }}">{{ $admin->name }}</a>
                                                @if($isSelf)<span class="pill pill-default" style="margin-left:6px;">You</span>@endif
                                            </b>
                                            <span>{{ $admin->email }}</span>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    @php($status = $admin->statusLabel())
                                    <span class="pill {{ $status === 'Active' ? 'pill-ok' : ($status === 'Expired' ? 'pill-expired' : 'pill-off') }}">{{ $status }}</span>
                                </td>
                                <td class="nowrap">
                                    @if($admin->expires_on === null)
                                        <span class="help">No expiry</span>
                                    @else
                                        {{ $admin->expires_on->format('M d, Y') }}
                                        @php($days = $admin->daysUntilExpiry())
                                        <div class="seats-sub">
                                            @if($days < 0)
                                                Expired {{ abs($days) }}d ago
                                            @elseif($days === 0)
                                                Expires today
                                            @else
                                                {{ $days }} days left
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="nowrap">
                                    {{ $admin->last_login_at?->format('M d, Y H:i') ?? 'Never' }}
                                </td>
                                <td class="nowrap">{{ $admin->created_at?->format('M d, Y') ?? '—' }}</td>
                                <td>
                                    <div class="row-actions">
                                        <a href="{{ route('platform.admins.show', $admin) }}" class="btn btn-sm btn-ghost">View</a>
                                        <a href="{{ route('platform.admins.edit', $admin) }}" class="btn btn-sm btn-ghost">Edit</a>

                                        @unless($isSelf)
                                            <form method="POST" action="{{ route('platform.admins.status', $admin) }}" class="inline">
                                                @csrf @method('PATCH')
                                                <button type="submit" class="btn btn-sm btn-ghost">
                                                    {{ $admin->is_active ? 'Disable' : 'Enable' }}
                                                </button>
                                            </form>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($admins->hasPages())
                <div class="pager">
                    {{ $admins->links() }}
                </div>
            @endif
        @endif
    </div>
@endsection

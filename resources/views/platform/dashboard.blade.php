@extends('platform.layout')

@section('title', 'Companies')

@section('content')
    <div class="page-head">
        <div>
            <h1>Companies</h1>
            <p>Manage tenant companies on {{ config('app.name', 'SamriddhiHR') }}.</p>
        </div>
        <a href="{{ route('platform.companies.create') }}" class="btn btn-primary">+ Add company</a>
    </div>

    <div class="stats">
        <div class="stat"><div class="n">{{ $stats['companies'] }}</div><div class="l">Companies</div></div>
        <div class="stat"><div class="n">{{ $stats['active'] }}</div><div class="l">Active</div></div>
        <div class="stat"><div class="n">{{ $stats['pending'] }}</div><div class="l">Pending</div></div>
        <div class="stat"><div class="n">{{ $stats['suspended'] }}</div><div class="l">Suspended</div></div>
        <div class="stat"><div class="n">{{ $stats['expired'] }}</div><div class="l">Expired</div></div>
        @if($stats['provisioning'] > 0)
            <div class="stat"><div class="n">{{ $stats['provisioning'] }}</div><div class="l">Provisioning</div></div>
        @endif
        <div class="stat"><div class="n">{{ $stats['users'] }}</div><div class="l">Users</div></div>
        <div class="stat"><div class="n">{{ $stats['employees'] }}</div><div class="l">Employees</div></div>
    </div>

    <form method="POST" action="{{ route('platform.stats.refresh') }}" style="margin:-8px 0 16px;">
        @csrf
        <button type="submit" class="btn btn-sm btn-ghost">↻ Refresh counts</button>
        <span class="help" style="margin-left:8px;">
            User and employee counts live in each company's own database and are
            cached here.
        </span>
    </form>

    <div class="card">
        <div class="tbl-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Company</th>
                        <th>Domain</th>
                        <th>Status</th>
                        <th>Start date</th>
                        <th>Expiry date</th>
                        <th>Users / limit</th>
                        <th>Employees</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($companies as $company)
                        <tr>
                            <td>
                                <div class="co-name">{{ $company->name }}</div>
                                <span class="help">{{ $company->getAttribute('tenancy_db_name') ?? '—' }}</span>
                            </td>
                            <td><span class="co-host">{{ $company->domain ?? '—' }}</span></td>
                            <td>
                                @if($company->status === 'provisioning')
                                    <span class="pill pill-pending">Provisioning</span>
                                @elseif($company->isExpired())
                                    <span class="pill pill-expired">Expired</span>
                                @elseif($company->isPending())
                                    <span class="pill pill-pending">Pending</span>
                                @elseif($company->status === 'active')
                                    <span class="pill pill-ok">Active</span>
                                @else
                                    <span class="pill pill-off">Suspended</span>
                                @endif
                            </td>
                            <td>{{ $company->starts_on?->format('M d, Y') ?? 'Not set' }}</td>
                            <td>{{ $company->expires_on?->format('M d, Y') ?? 'No expiry' }}</td>
                            <td class="nowrap">
                                {{ $company->users_count ?? '—' }}@if($company->hasUserLimit()) / {{ $company->user_limit }}@endif
                                @if($company->hasUserLimit() && $company->seatsRemaining() === 0)
                                    <div class="seats-sub" style="color:var(--danger);">Limit reached</div>
                                @elseif(! $company->hasUserLimit())
                                    <div class="seats-sub">Unlimited</div>
                                @endif
                            </td>
                            <td>{{ $company->employees_count ?? '—' }}</td>
                            <td>
                                <div class="row-actions">
                                    <a href="{{ route('platform.companies.edit', $company) }}" class="btn btn-sm btn-ghost">Edit</a>

                                    <form method="POST" action="{{ route('platform.companies.status', $company) }}" class="inline">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-ghost">
                                            {{ $company->status === 'active' ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('platform.companies.destroy', $company) }}" class="inline"
                                          onsubmit="return confirm('Delete {{ $company->name }} and DROP its entire database? This cannot be undone.');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection

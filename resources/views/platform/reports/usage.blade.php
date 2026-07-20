@extends('platform.layout')

@section('title', 'Usage report')

@section('content')
    <div class="page-head">
        <div>
            <h1>Usage report</h1>
            <p>Accounts created per company against their seat limit, and where each sits in its subscription.</p>
        </div>
        <a href="{{ route('platform.reports.usage.export') }}" class="btn btn-primary">↓ Download CSV</a>
    </div>

    <div class="stats">
        <div class="stat"><div class="n">{{ $totals['companies'] }}</div><div class="l">Companies</div></div>
        <div class="stat"><div class="n">{{ $totals['users'] }}</div><div class="l">Accounts created</div></div>
        <div class="stat"><div class="n">{{ $totals['seats'] ?: '—' }}</div><div class="l">Seats allocated</div></div>
        <div class="stat"><div class="n">{{ $totals['employees'] }}</div><div class="l">Employees</div></div>
        <div class="stat"><div class="n">{{ $totals['at_limit'] }}</div><div class="l">At limit</div></div>
        <div class="stat"><div class="n">{{ $totals['expiring_soon'] }}</div><div class="l">Expiring ≤30d</div></div>
    </div>

    <div class="toolbar">
        <form method="POST" action="{{ route('platform.stats.refresh') }}" class="inline">
            @csrf
            <button type="submit" class="btn btn-sm btn-ghost">↻ Refresh counts</button>
        </form>
        <span class="help">
            Counts live in each company's own database and are cached here — refresh before relying on the numbers.
        </span>
    </div>

    <div class="card">
        <div class="tbl-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Company</th>
                        <th>Status</th>
                        <th>Accounts / limit</th>
                        <th>Seats left</th>
                        <th>Employees</th>
                        <th>Start</th>
                        <th>Expiry</th>
                        <th>Counts synced</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>
                                <div class="co-name">{{ $row['name'] }}</div>
                                <span class="co-host">{{ $row['domain'] }}</span>
                            </td>
                            <td>
                                @php($pill = match($row['status']) {
                                    'Active' => 'pill-ok',
                                    'Expired' => 'pill-expired',
                                    'Suspended' => 'pill-off',
                                    default => 'pill-pending',
                                })
                                <span class="pill {{ $pill }}">{{ $row['status'] }}</span>
                            </td>
                            <td>
                                @if($row['limit'] === null)
                                    <span class="seats">{{ $row['users'] }}</span>
                                    <div class="seats-sub">Unlimited</div>
                                @else
                                    <span class="seats">{{ $row['users'] }} / {{ $row['limit'] }}</span>
                                    <div class="seats-sub">{{ $row['usage_percent'] }}% used</div>
                                    <div class="meter {{ $row['at_limit'] ? 'is-full' : ($row['usage_percent'] >= 80 ? 'is-warn' : '') }}">
                                        <i style="width: {{ $row['usage_percent'] }}%"></i>
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if($row['seats_left'] === null)
                                    <span class="help">—</span>
                                @elseif($row['at_limit'])
                                    <span class="pill pill-expired">Full</span>
                                @else
                                    {{ $row['seats_left'] }}
                                @endif
                            </td>
                            <td>{{ $row['employees'] }}</td>
                            <td class="nowrap">{{ $row['starts_on'] ?: 'Not set' }}</td>
                            <td class="nowrap">
                                @if($row['expires_on'] === '')
                                    <span class="help">No expiry</span>
                                @else
                                    {{ $row['expires_on'] }}
                                    <div class="seats-sub">
                                        @if($row['days_left'] < 0)
                                            Expired {{ abs($row['days_left']) }}d ago
                                        @elseif($row['days_left'] === 0)
                                            Expires today
                                        @else
                                            {{ $row['days_left'] }} days left
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td class="nowrap help">{{ $row['synced_at'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align:center; padding:32px; color:var(--ink-3);">
                                No companies yet. <a href="{{ route('platform.companies.create') }}">Add the first one</a>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

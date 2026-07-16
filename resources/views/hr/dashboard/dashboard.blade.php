@extends('layouts.backend')

@section('content')
@php
    $perms = $dashboardPermissions ?? [];
    $can = fn (string $permission): bool => $perms[$permission] ?? false;
    $scopeLabel = match ($dashboardScope ?? 'self') {
        'all' => __('Company overview'),
        'department' => __('Department overview'),
        default => __('Personal overview'),
    };
    $visibleCards = collect($summaryCards ?? [])->filter(fn ($card) => $can($card['permission'] ?? ''))->values();
    $attendance = $attendanceSummary ?? ['present' => 0, 'absent' => 0, 'late' => 0, 'leave' => 0];
    $departmentRows = collect($departmentChart ?? []);
@endphp

<div class="wrapper-page">
    <div class="page-title">
        <h1><i class="icon-grid"></i> {{ __('Dashboard') }}</h1>
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid hr-dashboard">
            <div class="dashboard-hero">
                <div>
                    <span class="dashboard-kicker">{{ __('HR dashboard') }}</span>
                    <h2>{{ $scopeLabel }}</h2>
                    <p>{{ __('Basic employee visibility, daily attendance, notices, notes, and upcoming events.') }}</p>
                </div>
                <div class="dashboard-hero-date">
                    <span>{{ now()->format('l') }}</span>
                    <strong>{{ now()->format('M d, Y') }}</strong>
                </div>
            </div>

            @if($visibleCards->isNotEmpty())
                <div class="dashboard-card-grid">
                    @foreach($visibleCards as $card)
                        <div class="dashboard-stat dashboard-stat-{{ $card['tone'] ?? 'neutral' }}">
                            <div>
                                <span>{{ __($card['label']) }}</span>
                                <strong>{{ is_float($card['value']) ? number_format($card['value'], 1) : number_format((int) $card['value']) }}</strong>
                            </div>
                            <i class="{{ $card['icon'] }}"></i>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="row g-3 align-items-start">
                <div class="col-xl-8">
                    <div class="row g-3">
                        @if($can('dashboard.attendance_chart'))
                            <div class="{{ $can('dashboard.department_chart') && ($dashboardScope ?? 'self') !== 'self' ? 'col-lg-6' : 'col-12' }}">
                                <div class="dashboard-panel">
                                    <div class="dashboard-panel-header">
                                        <div>
                                            <h3>{{ ($dashboardScope ?? 'self') === 'self' ? __('My Monthly Attendance Summary') : __('Attendance Summary') }}</h3>
                                            <p>{{ __('Present, absent, late, and leave counts.') }}</p>
                                        </div>
                                    </div>
                                    <div class="dashboard-chart-wrap">
                                        <canvas
                                            id="attendanceSummaryChart"
                                            data-present="{{ (int) ($attendance['present'] ?? 0) }}"
                                            data-absent="{{ (int) ($attendance['absent'] ?? 0) }}"
                                            data-late="{{ (int) ($attendance['late'] ?? 0) }}"
                                            data-leave="{{ (int) ($attendance['leave'] ?? 0) }}"
                                        ></canvas>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if($can('dashboard.department_chart') && ($dashboardScope ?? 'self') !== 'self')
                            <div class="col-lg-6">
                                <div class="dashboard-panel">
                                    <div class="dashboard-panel-header">
                                        <div>
                                            <h3>{{ __('Department-wise Employees') }}</h3>
                                            <p>{{ __('Active employee count by department.') }}</p>
                                        </div>
                                    </div>
                                    <div class="dashboard-chart-wrap">
                                        <canvas
                                            id="departmentEmployeeChart"
                                            data-labels="{{ $departmentRows->pluck('label')->values()->toJson() }}"
                                            data-values="{{ $departmentRows->pluck('value')->map(fn ($value) => (int) $value)->values()->toJson() }}"
                                        ></canvas>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if($can('dashboard.today_attendance_table'))
                            <div class="col-12">
                                <div class="dashboard-panel">
                                    <div class="dashboard-panel-header">
                                        <div>
                                            <h3>{{ ($dashboardScope ?? 'self') === 'self' ? __('My Recent Attendance') : (($dashboardScope ?? 'self') === 'department' ? __('Today Team Attendance') : __('Today Attendance')) }}</h3>
                                            <p>{{ __('Latest attendance entries within your dashboard scope.') }}</p>
                                        </div>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table dashboard-table">
                                            <thead>
                                                <tr>
                                                    <th>{{ __('Employee') }}</th>
                                                    <th>{{ __('Department') }}</th>
                                                    <th>{{ __('Status') }}</th>
                                                    <th>{{ __('Check In') }}</th>
                                                    <th>{{ __('Check Out') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse(($todayAttendanceRows ?? collect()) as $row)
                                                    <tr>
                                                        <td>
                                                            <strong>{{ trim($row->employee?->first_name . ' ' . $row->employee?->last_name) ?: '-' }}</strong>
                                                            <div class="small text-muted">{{ $row->employee?->employee_code }}</div>
                                                        </td>
                                                        <td>{{ $row->employee?->department?->name ?? __('Unassigned') }}</td>
                                                        <td><span class="dashboard-status status-{{ $row->status }}">{{ __(ucfirst($row->status)) }}</span></td>
                                                        <td>{{ $row->check_in_at?->format('h:i A') ?? '-' }}</td>
                                                        <td>{{ $row->check_out_at?->format('h:i A') ?? '-' }}</td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="5" class="text-center text-muted">{{ __('No attendance entries available.') }}</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="col-lg-6">
                            @if($can('dashboard.pending_leave_table'))
                                <div class="dashboard-panel">
                                    <div class="dashboard-panel-header">
                                        <div>
                                            <h3>{{ ($dashboardScope ?? 'self') === 'self' ? __('My Leave Requests') : (($dashboardScope ?? 'self') === 'department' ? __('Team Pending Leave Requests') : __('Pending Leave Requests')) }}</h3>
                                            <p>{{ __('Open leave requests for this dashboard scope.') }}</p>
                                        </div>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table dashboard-table">
                                            <thead>
                                                <tr>
                                                    <th>{{ __('Employee') }}</th>
                                                    <th>{{ __('Leave') }}</th>
                                                    <th>{{ __('Dates') }}</th>
                                                    <th>{{ __('Days') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse(($pendingLeaveRows ?? collect()) as $leave)
                                                    <tr>
                                                        <td>{{ trim($leave->employee?->first_name . ' ' . $leave->employee?->last_name) ?: '-' }}</td>
                                                        <td>{{ $leave->leaveCategory?->name ?? '-' }}</td>
                                                        <td>{{ $leave->start_date ? \Illuminate\Support\Carbon::parse($leave->start_date)->format('M d') : '-' }} - {{ $leave->end_date ? \Illuminate\Support\Carbon::parse($leave->end_date)->format('M d') : '-' }}</td>
                                                        <td>{{ number_format((float) $leave->total_days, 1) }}</td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="4" class="text-center text-muted">{{ __('No pending leave requests.') }}</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="col-lg-6">
                            @if($can('dashboard.upcoming_events_table') || ($canViewHolidays ?? false))
                                <div class="dashboard-panel">
                                    <div class="dashboard-panel-header">
                                        <div>
                                            <h3>{{ ($dashboardScope ?? 'self') === 'department' ? __('Upcoming Team Events') : __('Upcoming Holidays & Birthdays') }}</h3>
                                            <p>{{ __('Events in the next 45 days.') }}</p>
                                        </div>
                                    </div>
                                    <div class="dashboard-event-list">
                                        @forelse(($upcomingEvents ?? collect()) as $event)
                                            <div class="dashboard-event">
                                                <span>{{ __($event['type']) }}</span>
                                                <div>
                                                    <strong>{{ $event['title'] }}</strong>
                                                    <p>{{ $event['date']?->format('M d') ?? '-' }} · {{ __($event['meta']) }}</p>
                                                </div>
                                            </div>
                                        @empty
                                            <div class="dashboard-empty">{{ __('No upcoming holidays or birthdays.') }}</div>
                                        @endforelse
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-xl-4">
                    <div class="dashboard-side-stack">
                        @if($can('dashboard.notice_board') || ($canViewAnnouncements ?? false))
                            <div class="dashboard-panel">
                                <div class="dashboard-panel-header">
                                    <div>
                                        <h3>{{ __('Notice Board') }}</h3>
                                        <p>{{ __('Published HR/Admin notices.') }}</p>
                                    </div>
                                    <div class="d-flex gap-2">
                                        @if($canViewAnnouncements ?? false)
                                            <a href="{{ route('announcements.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('View') }}</a>
                                        @endif
                                        @if($canCreateAnnouncement ?? false)
                                            <a href="{{ route('announcements.create') }}" class="btn btn-sm btn-primary">{{ __('Add') }}</a>
                                        @endif
                                    </div>
                                </div>
                                <div class="dashboard-notice-list">
                                    @forelse(($latestAnnouncements ?? collect()) as $item)
                                        <a href="{{ ($canViewAnnouncements ?? false) ? route('announcements.show', $item) : '#' }}" class="dashboard-notice">
                                            <span class="{{ $item->announcement_type === 'notice' ? 'notice' : 'announcement' }}">
                                                {{ __(ucfirst($item->announcement_type)) }}
                                            </span>
                                            <strong>{{ $item->title }}</strong>
                                            <small>{{ $item->publish_at?->format('M d, Y') ?? __('Draft date unavailable') }}</small>
                                        </a>
                                    @empty
                                        <div class="dashboard-empty">{{ __('No active notices available.') }}</div>
                                    @endforelse
                                </div>
                            </div>
                        @endif

                        @if($can('dashboard.quick_notes'))
                            <div class="dashboard-panel">
                                <div class="dashboard-panel-header">
                                    <div>
                                        <h3>{{ __('Quick Notes') }}</h3>
                                        <p>{{ __('Private notes for your own follow-up.') }}</p>
                                    </div>
                                    <i class="icon-notebook dashboard-panel-icon"></i>
                                </div>
                                <ul
                                    class="dashboard-note-list"
                                    id="quick-note-list"
                                    data-csrf="{{ csrf_token() }}"
                                    data-can-update="{{ ($canUpdatePrivateNotes ?? false) ? '1' : '0' }}"
                                    data-can-delete="{{ ($canDeletePrivateNotes ?? false) ? '1' : '0' }}"
                                >
                                    @if(!($canViewPrivateNotes ?? false))
                                        <li class="todo-item quick-note-empty dashboard-empty">{{ __('You do not have permission to view private notes.') }}</li>
                                    @elseif(($privateNotes ?? collect())->isEmpty())
                                        <li class="todo-item quick-note-empty dashboard-empty">{{ __('No notes yet. Add your first private note below.') }}</li>
                                    @else
                                        @foreach(($privateNotes ?? collect()) as $note)
                                            @php($noteInputId = 'quick_note_' . $note->id)
                                            <li class="todo-item dashboard-note" data-note-id="{{ $note->id }}">
                                                <div class="d-flex align-items-start gap-2">
                                                    @if($canUpdatePrivateNotes ?? false)
                                                        <form method="POST" action="{{ route('dashboard.quick-notes.toggle', $note) }}" class="checkbox checkbox-default pt-1 quick-note-toggle-form">
                                                            @csrf
                                                            @method('PATCH')
                                                            <input class="to-do quick-note-toggle" type="checkbox" id="{{ $noteInputId }}" {{ $note->is_completed ? 'checked' : '' }}>
                                                            <label for="{{ $noteInputId }}"></label>
                                                        </form>
                                                    @endif
                                                    <div class="flex-grow-1">
                                                        <div class="fw-semibold quick-note-title {{ $note->is_completed ? 'text-decoration-line-through text-muted' : '' }}">{{ $note->title }}</div>
                                                        <div class="small quick-note-body {{ $note->is_completed ? 'text-decoration-line-through text-muted' : 'text-muted' }}">{{ $note->note_body }}</div>
                                                    </div>
                                                    @if($canUpdatePrivateNotes ?? false)
                                                        <button type="button" class="btn btn-sm btn-outline-secondary quick-note-edit-btn" title="{{ __('Edit note') }}" data-action="{{ route('dashboard.quick-notes.update', $note) }}">
                                                            <i class="icon-pencil"></i>
                                                        </button>
                                                    @endif
                                                    @if($canDeletePrivateNotes ?? false)
                                                        <form method="POST" action="{{ route('dashboard.quick-notes.delete', $note) }}" class="quick-note-delete-form">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Delete note') }}"><i class="icon-trash"></i></button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </li>
                                        @endforeach
                                    @endif
                                </ul>
                                @if($canCreatePrivateNotes ?? false)
                                    <form method="POST" action="{{ route('dashboard.quick-notes.store') }}" id="add_todo" class="quick-note-add-form dashboard-note-form">
                                        @csrf
                                        <div class="input-group">
                                            <input type="text" name="note_body" class="form-control" placeholder="{{ __('Add private note') }}" required maxlength="2000">
                                            <button type="submit" class="btn btn-primary"><i class="fa fa-plus"></i></button>
                                        </div>
                                    </form>
                                @endif
                            </div>
                        @endif

                        @if($can('dashboard.basic_alerts'))
                            <div class="dashboard-panel">
                                <div class="dashboard-panel-header">
                                    <div>
                                        <h3>{{ __('Basic Alerts') }}</h3>
                                        <p>{{ __('Simple items needing attention.') }}</p>
                                    </div>
                                </div>
                                <div class="dashboard-alert-list">
                                    @forelse(($basicAlerts ?? []) as $alert)
                                        <div class="dashboard-alert alert-{{ $alert['tone'] }}">
                                            <i class="icon-info"></i>
                                            <span>{{ $alert['label'] }}</span>
                                        </div>
                                    @empty
                                        <div class="dashboard-empty">{{ __('No alerts right now.') }}</div>
                                    @endforelse
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var attendanceCanvas = document.getElementById('attendanceSummaryChart');
    if (attendanceCanvas && window.Chart) {
        new Chart(attendanceCanvas.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: {!! json_encode([__('Present'), __('Absent'), __('Late'), __('On Leave')]) !!},
                datasets: [{
                    data: [
                        Number(attendanceCanvas.dataset.present || 0),
                        Number(attendanceCanvas.dataset.absent || 0),
                        Number(attendanceCanvas.dataset.late || 0),
                        Number(attendanceCanvas.dataset.leave || 0)
                    ],
                    backgroundColor: ['#1f9d72', '#c24141', '#d08813', '#0f8f8c'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: { position: 'bottom' },
                cutoutPercentage: 62
            }
        });
    }

    var departmentCanvas = document.getElementById('departmentEmployeeChart');
    if (departmentCanvas && window.Chart) {
        new Chart(departmentCanvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: JSON.parse(departmentCanvas.dataset.labels || '[]'),
                datasets: [{
                    label: @json(__('Employees')),
                    data: JSON.parse(departmentCanvas.dataset.values || '[]'),
                    backgroundColor: '#0f8f8c',
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: { display: false },
                scales: {
                    yAxes: [{ ticks: { beginAtZero: true, precision: 0 } }]
                }
            }
        });
    }
})();

(function () {
    var list = document.getElementById('quick-note-list');
    var addForm = document.querySelector('.quick-note-add-form');
    var csrf = list ? (list.getAttribute('data-csrf') || '') : '';
    var canUpdate = list ? list.getAttribute('data-can-update') === '1' : false;
    var canDelete = list ? list.getAttribute('data-can-delete') === '1' : false;

    function fetchForm(form) {
        return fetch(form.action, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: new FormData(form)
        }).then(function (res) { return res.json(); });
    }

    function fetchAction(url, payload) {
        var formData = new FormData();
        Object.keys(payload).forEach(function (key) {
            formData.append(key, payload[key]);
        });
        return fetch(url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: formData
        }).then(function (res) { return res.json(); });
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function ensureEmptyState() {
        if (!list) return;
        var items = list.querySelectorAll('.todo-item:not(.quick-note-empty)');
        var empty = list.querySelector('.quick-note-empty');
        if (items.length === 0 && !empty) {
            var emptyLi = document.createElement('li');
            emptyLi.className = 'todo-item quick-note-empty dashboard-empty';
            emptyLi.innerHTML = @json(__('No notes yet. Add your first private note below.'));
            list.appendChild(emptyLi);
        }
        if (items.length > 0 && empty) {
            empty.remove();
        }
    }

    function makeRowHtml(id, title, body) {
        var html = '<div class="d-flex align-items-start gap-2">';
        if (canUpdate) {
            html += '<form method="POST" action="/dashboard/quick-notes/' + id + '/toggle" class="checkbox checkbox-default pt-1 quick-note-toggle-form">' +
                '<input type="hidden" name="_token" value="' + csrf + '">' +
                '<input type="hidden" name="_method" value="PATCH">' +
                '<input class="to-do quick-note-toggle" type="checkbox" id="quick_note_' + id + '">' +
                '<label for="quick_note_' + id + '"></label>' +
                '</form>';
        }
        html += '<div class="flex-grow-1">' +
            '<div class="fw-semibold quick-note-title">' + title + '</div>' +
            '<div class="small quick-note-body text-muted">' + body + '</div>' +
            '</div>';
        if (canUpdate) {
            html += '<button type="button" class="btn btn-sm btn-outline-secondary quick-note-edit-btn" title="{{ __('Edit note') }}" data-action="/dashboard/quick-notes/' + id + '">' +
                '<i class="icon-pencil"></i>' +
                '</button>';
        }
        if (canDelete) {
            html += '<form method="POST" action="/dashboard/quick-notes/' + id + '" class="quick-note-delete-form">' +
                '<input type="hidden" name="_token" value="' + csrf + '">' +
                '<input type="hidden" name="_method" value="DELETE">' +
                '<button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Delete note') }}"><i class="icon-trash"></i></button>' +
                '</form>';
        }
        return html + '</div>';
    }

    if (addForm) {
        addForm.addEventListener('submit', function (event) {
            event.preventDefault();
            fetchForm(addForm).then(function (json) {
                if (!json || !json.ok || !json.note || !list) return;
                list.querySelectorAll('.quick-note-empty').forEach(function (el) { el.remove(); });
                var row = document.createElement('li');
                row.className = 'todo-item dashboard-note';
                row.setAttribute('data-note-id', String(json.note.id));
                row.innerHTML = makeRowHtml(Number(json.note.id), escapeHtml(json.note.title || ''), escapeHtml(json.note.note_body || ''));
                list.prepend(row);
                ensureEmptyState();
                addForm.reset();
            }).catch(function () {});
        });
    }

    document.addEventListener('change', function (event) {
        var checkbox = event.target.closest('.quick-note-toggle');
        if (!checkbox) return;
        var form = checkbox.closest('.quick-note-toggle-form');
        var item = checkbox.closest('.todo-item');
        if (!form || !item) return;

        fetchForm(form).then(function (json) {
            if (!json || !json.ok || !json.note) return;
            var done = Boolean(json.note.is_completed);
            var title = item.querySelector('.quick-note-title');
            var body = item.querySelector('.quick-note-body');
            if (title) {
                title.classList.toggle('text-decoration-line-through', done);
                title.classList.toggle('text-muted', done);
            }
            if (body) {
                body.classList.toggle('text-decoration-line-through', done);
                body.classList.add('text-muted');
            }
        }).catch(function () {
            checkbox.checked = !checkbox.checked;
        });
    });

    document.addEventListener('click', function (event) {
        var button = event.target.closest('.quick-note-edit-btn');
        if (!button) return;
        var item = button.closest('.todo-item');
        if (!item) return;
        var bodyNode = item.querySelector('.quick-note-body');
        var titleNode = item.querySelector('.quick-note-title');
        var nextBody = window.prompt(@json(__('Edit note')), bodyNode ? (bodyNode.textContent || '').trim() : '');
        if (nextBody === null || nextBody.trim() === '') return;

        fetchAction(button.getAttribute('data-action') || '', {
            _token: csrf,
            _method: 'PATCH',
            title: titleNode ? (titleNode.textContent || '').trim() : '',
            note_body: nextBody.trim()
        }).then(function (json) {
            if (!json || !json.ok || !json.note) return;
            if (titleNode) titleNode.textContent = json.note.title || '';
            if (bodyNode) bodyNode.textContent = json.note.note_body || '';
        }).catch(function () {});
    });

    document.addEventListener('submit', function (event) {
        var form = event.target.closest('.quick-note-delete-form');
        if (!form) return;
        event.preventDefault();
        if (!window.confirm(@json(__('Delete this note permanently?')))) return;
        fetchForm(form).then(function (json) {
            if (!json || !json.ok) return;
            var item = form.closest('.todo-item');
            if (item) item.remove();
            ensureEmptyState();
        }).catch(function () {});
    });

    ensureEmptyState();
})();
</script>
@endpush

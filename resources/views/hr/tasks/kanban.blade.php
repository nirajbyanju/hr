@extends('layouts.backend')
@section('title', 'Kanban Board')

@section('content')
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center">
        <h1><i class="icon-grid"></i> {{ __('Kanban Board') }}</h1>
        <a href="{{ route('tasks.index') }}" class="btn btn-custom-default"><i class="icon-arrow-left"></i> {{ __('Back to List') }}</a>
    </div>

    @include('partials.flash')

    <div class="page-content"><div class="container-fluid">
        <div id="kanban-error" class="alert alert-danger d-none"></div>

        <div class="kanban-toolbar">
            <div class="kanban-search">
                <i class="icon-magnifier"></i>
                <input type="text" id="kanban-search-input" placeholder="{{ __('Search by task or assignee...') }}">
            </div>
            <div class="kanban-legend">
                <span class="kanban-legend-item"><i class="kanban-age-dot kanban-age-fresh"></i>{{ __('Fresh') }}</span>
                <span class="kanban-legend-item"><i class="kanban-age-dot kanban-age-warning"></i>{{ __('Aging') }}</span>
                <span class="kanban-legend-item"><i class="kanban-age-dot kanban-age-stale"></i>{{ __('Stale') }}</span>
            </div>
        </div>

        <div class="kanban-board">
            @foreach($columns as $key => $column)
                <div class="kanban-column" data-column="{{ $key }}" data-droppable="{{ $column['drop_target'] ? '1' : '0' }}">
                    <div class="kanban-column-header" style="--kanban-accent: {{ $column['accent'] }};">
                        <span class="kanban-column-dot"></span>
                        <span class="kanban-column-title">{{ $column['label'] }}</span>
                        <span class="kanban-column-count">{{ count($column['cards']) }}</span>
                    </div>
                    <div class="kanban-drop-zone">
                        @forelse($column['cards'] as $assignment)
                            @php
                                $initials = strtoupper(mb_substr($assignment->employee?->first_name ?? '', 0, 1) . mb_substr($assignment->employee?->last_name ?? '', 0, 1));
                                $fullName = trim(($assignment->employee?->first_name ?? '').' '.($assignment->employee?->last_name ?? '')) ?: __('Unassigned');
                                $isOverdue = ! $column['is_terminal'] && $assignment->task?->due_date && \Illuminate\Support\Carbon::parse($assignment->task->due_date)->isPast();
                                $agingLevel = $column['is_terminal'] ? 'fresh' : $assignment->stage_aging_level;
                            @endphp
                            <div class="kanban-card" draggable="true"
                                 data-assignment-id="{{ $assignment->id }}"
                                 data-move-url="{{ route('tasks.kanban.move', $assignment) }}"
                                 data-entered-at="{{ optional($assignment->stage_entered_at)->toIso8601String() }}"
                                 data-terminal="{{ $column['is_terminal'] ? '1' : '0' }}"
                                 style="--kanban-priority-color: {{ $assignment->task?->priority?->color ?? 'var(--hr-border-strong)' }};">
                                <div class="kanban-card-top">
                                    <a href="{{ route('tasks.show', $assignment->task_id) }}" class="kanban-card-title">#{{ $assignment->task_id }} {{ $assignment->task?->title }}</a>
                                </div>
                                <div class="kanban-card-meta">
                                    <span class="kanban-avatar" title="{{ $fullName }}">{{ $initials ?: '?' }}</span>
                                    <span class="kanban-assignee-name">{{ $fullName }}</span>
                                    @if($assignment->task?->priority)
                                        <span class="kanban-priority-badge" style="background-color: color-mix(in srgb, {{ $assignment->task->priority->color }} 14%, white); color: {{ $assignment->task->priority->color }};">{{ $assignment->task->priority->name }}</span>
                                    @endif
                                </div>
                                <div class="kanban-card-footer">
                                    <span class="kanban-age-pill kanban-age-{{ $agingLevel }}" title="{{ __('In this stage since') }} {{ optional($assignment->stage_entered_at)->format('M d, Y H:i') }}">
                                        <i class="icon-clock"></i>
                                        <span class="kanban-age-text">{{ $assignment->stage_duration_label }}</span>
                                    </span>
                                    @if($assignment->task?->due_date)
                                        <span class="kanban-due {{ $isOverdue ? 'kanban-due-overdue' : '' }}">
                                            <i class="icon-calendar"></i> {{ \Illuminate\Support\Carbon::parse($assignment->task->due_date)->format('M d') }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="kanban-empty">{{ __('No tasks') }}</div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </div></div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var errorBox = document.getElementById('kanban-error');
    var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    var dragged = null;

    // ---- Time-in-stage: compact "Xd Yh" labels computed client-side so they stay live
    // between page loads without polling the server. Mirrors formatDuration() in
    // TaskKanbanController so the two never disagree by more than the refresh interval.
    function formatDuration(enteredAt) {
        var seconds = Math.max(0, Math.floor((Date.now() - new Date(enteredAt).getTime()) / 1000));

        if (seconds < 60) {
            return '{{ __('Just now') }}';
        }

        var days = Math.floor(seconds / 86400);
        var hours = Math.floor((seconds % 86400) / 3600);
        var minutes = Math.floor((seconds % 3600) / 60);

        if (days > 0) {
            return hours > 0 ? days + 'd ' + hours + 'h' : days + 'd';
        }

        if (hours > 0) {
            return minutes > 0 ? hours + 'h ' + minutes + 'm' : hours + 'h';
        }

        return minutes + 'm';
    }

    function agingLevel(enteredAt) {
        var hours = (Date.now() - new Date(enteredAt).getTime()) / 3600000;
        if (hours < 24) return 'fresh';
        if (hours < 72) return 'warning';
        return 'stale';
    }

    function refreshAgeBadges() {
        document.querySelectorAll('.kanban-card').forEach(function (card) {
            var enteredAt = card.dataset.enteredAt;
            if (!enteredAt) {
                return;
            }

            var pill = card.querySelector('.kanban-age-pill');
            var text = card.querySelector('.kanban-age-text');
            if (!pill || !text) {
                return;
            }

            text.textContent = formatDuration(enteredAt);

            if (card.dataset.terminal !== '1') {
                var level = agingLevel(enteredAt);
                pill.classList.remove('kanban-age-fresh', 'kanban-age-warning', 'kanban-age-stale');
                pill.classList.add('kanban-age-' + level);
            }
        });
    }

    refreshAgeBadges();
    setInterval(refreshAgeBadges, 60000);

    function updateColumnCount(column) {
        var count = column.querySelector('.kanban-drop-zone').querySelectorAll('.kanban-card').length;
        var badge = column.querySelector('.kanban-column-count');
        if (badge) {
            badge.textContent = count;
        }
    }

    // ---- Client-side search filter (title or assignee name), no server round-trip.
    var searchInput = document.getElementById('kanban-search-input');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            var term = searchInput.value.trim().toLowerCase();
            document.querySelectorAll('.kanban-card').forEach(function (card) {
                var haystack = card.textContent.toLowerCase();
                card.classList.toggle('kanban-card-hidden', term !== '' && haystack.indexOf(term) === -1);
            });
        });
    }

    document.querySelectorAll('.kanban-card').forEach(function (card) {
        card.addEventListener('dragstart', function () {
            dragged = card;
            card.classList.add('kanban-card-dragging');
        });
        card.addEventListener('dragend', function () {
            card.classList.remove('kanban-card-dragging');
        });
    });

    document.querySelectorAll('.kanban-column').forEach(function (column) {
        var zone = column.querySelector('.kanban-drop-zone');
        zone.addEventListener('dragover', function (e) {
            if (column.dataset.droppable === '1') {
                e.preventDefault();
                zone.classList.add('kanban-drop-zone-active');
            }
        });
        zone.addEventListener('dragleave', function () {
            zone.classList.remove('kanban-drop-zone-active');
        });
        zone.addEventListener('drop', function (e) {
            e.preventDefault();
            zone.classList.remove('kanban-drop-zone-active');

            if (!dragged || column.dataset.droppable !== '1') {
                return;
            }

            // Dropping a card back into the column it already sits in is a no-op, not an error.
            if (dragged.parentElement === zone) {
                return;
            }

            errorBox.classList.add('d-none');

            var card = dragged;
            var fromColumn = card.closest('.kanban-column');
            var url = card.dataset.moveUrl;
            var targetColumn = column.dataset.column;

            function send(reason) {
                var body = { column: targetColumn };
                if (reason) {
                    body.reason = reason;
                }

                return fetch(url, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(body),
                }).then(function (response) {
                    return response.json().then(function (data) {
                        return { ok: response.ok, data: data };
                    });
                });
            }

            function handle(result) {
                if (result.ok) {
                    zone.appendChild(card);
                    if (result.data.entered_at) {
                        card.dataset.enteredAt = result.data.entered_at;
                        card.dataset.terminal = column.dataset.column === 'completed' || column.dataset.column === 'backlog' ? '1' : '0';
                    }
                    var text = card.querySelector('.kanban-age-text');
                    var pill = card.querySelector('.kanban-age-pill');
                    if (text) {
                        text.textContent = result.data.stage_duration_label || '{{ __('Just now') }}';
                    }
                    if (pill) {
                        pill.classList.remove('kanban-age-fresh', 'kanban-age-warning', 'kanban-age-stale');
                        pill.classList.add('kanban-age-' + (result.data.stage_aging_level || 'fresh'));
                    }
                    if (fromColumn) {
                        updateColumnCount(fromColumn);
                    }
                    updateColumnCount(column);
                    return;
                }

                // The workflow needs a reason for this move (hold / reopen / request changes).
                if (result.data.reason_required) {
                    var reason = window.prompt('{{ __('Please give a reason for this move:') }}');
                    if (reason && reason.trim() !== '') {
                        return send(reason.trim()).then(handle);
                    }
                    return;
                }

                errorBox.textContent = result.data.message || '{{ __('This move is not allowed.') }}';
                errorBox.classList.remove('d-none');
            }

            send(null)
                .then(handle)
                .catch(function () {
                    errorBox.textContent = '{{ __('Something went wrong. Please try again.') }}';
                    errorBox.classList.remove('d-none');
                });
        });
    });
})();
</script>
@endpush

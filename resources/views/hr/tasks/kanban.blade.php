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
        <div class="kanban-board d-flex gap-3" style="overflow-x: auto;">
            @foreach($columns as $key => $column)
                <div class="kanban-column card no-border" style="min-width: 260px; flex: 1;" data-column="{{ $key }}" data-droppable="{{ $column['drop_target'] ? '1' : '0' }}">
                    <div class="content_wrapper content-padded">
                        <h6 class="table_banner_title mb-2">{{ $column['label'] }} <span class="badge bg-secondary">{{ count($column['cards']) }}</span></h6>
                        <div class="kanban-drop-zone" style="min-height: 300px;">
                            @foreach($column['cards'] as $assignment)
                                <div class="kanban-card card mb-2" draggable="true"
                                     data-assignment-id="{{ $assignment->id }}"
                                     data-move-url="{{ route('tasks.kanban.move', $assignment) }}">
                                    <div class="content_wrapper content-padded p-2">
                                        <a href="{{ route('tasks.show', $assignment->task_id) }}" class="d-block fw-bold">#{{ $assignment->task_id }} {{ $assignment->task?->title }}</a>
                                        <div class="small">{{ trim(($assignment->employee?->first_name ?? '').' '.($assignment->employee?->last_name ?? '')) }}</div>
                                        <div class="d-flex justify-content-between align-items-center mt-1">
                                            @if($assignment->task?->priority)<span class="badge" style="background-color: {{ $assignment->task->priority->color }}">{{ $assignment->task->priority->name }}</span>@endif
                                            <span class="small text-muted">{{ $assignment->task?->due_date ?? '' }}</span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
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

    document.querySelectorAll('.kanban-card').forEach(function (card) {
        card.addEventListener('dragstart', function () {
            dragged = card;
            card.classList.add('opacity-50');
        });
        card.addEventListener('dragend', function () {
            card.classList.remove('opacity-50');
        });
    });

    document.querySelectorAll('.kanban-column').forEach(function (column) {
        var zone = column.querySelector('.kanban-drop-zone');
        zone.addEventListener('dragover', function (e) {
            if (column.dataset.droppable === '1') {
                e.preventDefault();
            }
        });
        zone.addEventListener('drop', function (e) {
            e.preventDefault();
            if (!dragged || column.dataset.droppable !== '1') {
                return;
            }

            // Dropping a card back into the column it already sits in is a no-op, not an error.
            if (dragged.parentElement === zone) {
                return;
            }

            errorBox.classList.add('d-none');

            var card = dragged;
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

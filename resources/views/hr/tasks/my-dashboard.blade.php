@extends('layouts.backend')
@section('title', 'My Tasks')

@php
    $bucketLabels = [
        'assigned_today' => __('Assigned Today'),
        'pending' => __('Pending'),
        'in_progress' => __('In Progress'),
        'on_hold' => __('On Hold'),
        'review' => __('Review'),
        'completed' => __('Completed'),
        'overdue' => __('Overdue'),
    ];
    $bucketColors = [
        'assigned_today' => 'bg-primary',
        'pending' => 'bg-secondary',
        'in_progress' => 'bg-info',
        'on_hold' => 'bg-warning',
        'review' => 'bg-purple',
        'completed' => 'bg-success',
        'overdue' => 'bg-danger',
    ];
@endphp

@section('content')
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center">
        <h1><i class="icon-user"></i> {{ __('My Tasks') }}</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('tasks.kanban') }}" class="btn btn-custom-default"><i class="icon-grid"></i> {{ __('Kanban') }}</a>
            <a href="{{ route('tasks.index') }}" class="btn btn-custom-default"><i class="icon-list"></i> {{ __('All Tasks') }}</a>
        </div>
    </div>

    @include('partials.flash')

    <div class="page-content"><div class="container-fluid">
        <div class="row g-3 mb-3">
            @foreach($bucketLabels as $key => $label)
                <div class="col-md-3 col-sm-6">
                    <div class="card no-border"><div class="content_wrapper content-padded text-center">
                        <span class="badge {{ $bucketColors[$key] }} mb-2">{{ $label }}</span>
                        <h3 class="mb-0">{{ $buckets[$key]->count() }}</h3>
                    </div></div>
                </div>
            @endforeach
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <div class="card no-border"><div class="content_wrapper content-padded">
                    <h5 class="table_banner_title mb-3">{{ __('Upcoming Deadlines') }}</h5>
                    @forelse($upcomingDeadlines as $assignment)
                        <div class="border-bottom py-1"><a href="{{ route('tasks.show', $assignment->task_id) }}">#{{ $assignment->task_id }} {{ $assignment->task?->title }}</a> <span class="small text-muted">{{ $assignment->task?->due_date }}</span></div>
                    @empty
                        <p class="text-muted">{{ __('No upcoming deadlines.') }}</p>
                    @endforelse
                </div></div>
            </div>
            <div class="col-md-6">
                <div class="card no-border"><div class="content_wrapper content-padded">
                    <h5 class="table_banner_title mb-3">{{ __("Today's Tasks") }}</h5>
                    @forelse($todaysTasks as $assignment)
                        <div class="border-bottom py-1"><a href="{{ route('tasks.show', $assignment->task_id) }}">#{{ $assignment->task_id }} {{ $assignment->task?->title }}</a></div>
                    @empty
                        <p class="text-muted">{{ __('Nothing due today.') }}</p>
                    @endforelse
                </div></div>
            </div>
            <div class="col-md-6">
                <div class="card no-border"><div class="content_wrapper content-padded">
                    <h5 class="table_banner_title mb-3">{{ __('High Priority') }}</h5>
                    @forelse($highPriority as $assignment)
                        <div class="border-bottom py-1">
                            <a href="{{ route('tasks.show', $assignment->task_id) }}">#{{ $assignment->task_id }} {{ $assignment->task?->title }}</a>
                            @if($assignment->task?->priority)<span class="badge" style="background-color: {{ $assignment->task->priority->color }}">{{ $assignment->task->priority->name }}</span>@endif
                        </div>
                    @empty
                        <p class="text-muted">{{ __('No high priority tasks.') }}</p>
                    @endforelse
                </div></div>
            </div>
            <div class="col-md-6">
                <div class="card no-border"><div class="content_wrapper content-padded">
                    <h5 class="table_banner_title mb-3">{{ __('Recently Updated') }}</h5>
                    @forelse($recentlyUpdated as $assignment)
                        <div class="border-bottom py-1">
                            <a href="{{ route('tasks.show', $assignment->task_id) }}">#{{ $assignment->task_id }} {{ $assignment->task?->title }}</a>
                            @if($assignment->status)<span class="badge" style="background-color: {{ $assignment->status->color }}">{{ $assignment->status->name }}</span>@endif
                        </div>
                    @empty
                        <p class="text-muted">{{ __('No recent activity.') }}</p>
                    @endforelse
                </div></div>
            </div>
        </div>
    </div></div>
</div>
@endsection

@extends('layouts.backend')
@section('title', 'Projects')

@section('content')
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center">
        <h1><i class="icon-briefcase"></i> {{ __('Projects') }}</h1>
        @if(auth()->user()?->hasPermission('project.create'))
            <a href="{{ route('projects.create') }}" class="btn btn-custom"><i class="icon-plus"></i> {{ __('Add Project') }}</a>
        @endif
    </div>

    @include('partials.flash')

    <div class="page-content"><div class="container-fluid"><div><div class="content_wrapper content-padded">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-4"><input type="text" name="q" value="{{ $filters['q'] }}" class="form-control" placeholder="{{ __('Search name/code') }}"></div>
            <div class="col-md-2"><select name="team_id" class="form-control"><option value="0">{{ __('All Teams') }}</option>@foreach($teams as $team)<option value="{{ $team->id }}" {{ (int)$filters['team_id']===(int)$team->id?'selected':'' }}>{{ $team->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><select name="status" class="form-control"><option value="">{{ __('All Status') }}</option>@foreach(['draft','active','on_hold','completed','cancelled'] as $status)<option value="{{ $status }}" {{ $filters['status']===$status?'selected':'' }}>{{ __(ucfirst(str_replace('_',' ',$status))) }}</option>@endforeach</select></div>
            <div class="col-md-2"><select name="per_page" class="form-control">@foreach([10,20,50,100] as $size)<option value="{{ $size }}" {{ (int)$filters['per_page']===$size?'selected':'' }}>{{ $size }}</option>@endforeach</select></div>
            <div class="col-md-2 d-flex gap-2"><button type="submit" class="btn btn-custom"><i class="icon-magnifier"></i></button><a href="{{ route('projects.index') }}" class="btn btn-custom-default"><i class="icon-refresh"></i></a></div>
        </form>

        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Code') }}</th><th>{{ __('Team') }}</th><th>{{ __('Manager') }}</th><th>{{ __('Status') }}</th><th>{{ __('Progress') }}</th><th>{{ __('Tasks') }}</th><th>{{ __('Members') }}</th><th>{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @forelse($projects as $project)
                        @php($manager = trim(($project->manager?->first_name ?? '').' '.($project->manager?->last_name ?? '')))
                        <tr>
                            <td>{{ $project->name }}</td>
                            <td>{{ $project->project_code }}</td>
                            <td>{{ $project->team?->name ?? '-' }}</td>
                            <td>{{ $manager !== '' ? $manager : '-' }}</td>
                            <td>{{ __(ucfirst(str_replace('_',' ', $project->status))) }}</td>
                            <td>{{ (int) $project->progress_percent }}%</td>
                            <td>{{ $project->tasks_count }}</td>
                            <td>{{ $project->members_count }}</td>
                            <td class="action-buttons">
                                <a href="{{ route('projects.show', $project) }}" title="{{ __('View') }}"><i class="icon-eye"></i></a>
                                @if(auth()->user()?->hasPermission('project.manage-members'))<a href="{{ route('projects.members', $project) }}" title="{{ __('Members') }}"><i class="icon-people"></i></a>@endif
                                @if(auth()->user()?->hasPermission('project.update'))<a href="{{ route('projects.edit', $project) }}" title="{{ __('Edit') }}"><i class="icon-pencil"></i></a>@endif
                                @if(auth()->user()?->hasPermission('project.delete'))
                                <form method="POST" action="{{ route('projects.destroy', $project) }}" onsubmit="return confirm('Delete this project?');" class="d-inline">@csrf @method('DELETE')<button type="submit" title="{{ __('Delete') }}"><i class="icon-trash"></i></button></form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center">{{ __('No projects found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $projects->links('pagination::bootstrap-5') }}
    </div></div></div></div>
</div>
@endsection

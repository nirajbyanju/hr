@extends('layouts.backend')
@section('title', 'Teams')

@section('content')
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center">
        <h1><i class="icon-people"></i> {{ __('Teams') }}</h1>
        @if(auth()->user()?->hasPermission('team.create'))
            <a href="{{ route('teams.create') }}" class="btn btn-custom"><i class="icon-plus"></i> {{ __('Add Team') }}</a>
        @endif
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            <div class="card no-border">
                <div class="content_wrapper content-padded">
                    <form method="GET" class="row g-2 mb-3">
                        <div class="col-md-4">
                            <input type="text" name="q" value="{{ $filters['q'] }}" class="form-control" placeholder="{{ __('Search name/code') }}">
                        </div>
                        <div class="col-md-2">
                            <select name="department_id" class="form-control">
                                <option value="0">{{ __('All Departments') }}</option>
                                @foreach($departments as $department)
                                    <option value="{{ $department->id }}" {{ (int) $filters['department_id'] === (int) $department->id ? 'selected' : '' }}>{{ $department->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="status" class="form-control">
                                <option value="">{{ __('All Status') }}</option>
                                <option value="active" {{ $filters['status'] === 'active' ? 'selected' : '' }}>{{ __('Active') }}</option>
                                <option value="inactive" {{ $filters['status'] === 'inactive' ? 'selected' : '' }}>{{ __('Inactive') }}</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="per_page" class="form-control">
                                @foreach([10,20,50,100] as $size)
                                    <option value="{{ $size }}" {{ (int) $filters['per_page'] === $size ? 'selected' : '' }}>{{ $size }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2 d-flex gap-2">
                            <button type="submit" class="btn btn-custom"><i class="icon-magnifier"></i></button>
                            <a href="{{ route('teams.index') }}" class="btn btn-custom-default"><i class="icon-refresh"></i></a>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>{{ __('Name') }}</th>
                                    <th>{{ __('Code') }}</th>
                                    <th>{{ __('Primary Department') }}</th>
                                    <th>{{ __('Lead') }}</th>
                                    <th>{{ __('Members') }}</th>
                                    <th>{{ __('Projects') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($teams as $team)
                                    @php($lead = trim(($team->lead?->first_name ?? '').' '.($team->lead?->last_name ?? '')))
                                    <tr>
                                        <td>{{ $team->name }}</td>
                                        <td>{{ $team->code ?? '-' }}</td>
                                        <td>{{ $team->department?->name ?? '-' }}</td>
                                        <td>{{ $lead !== '' ? $lead : '-' }}</td>
                                        <td>{{ $team->members_count }}</td>
                                        <td>{{ $team->projects_count }}</td>
                                        <td>{{ $team->is_active ? __('Active') : __('Inactive') }}</td>
                                        <td class="action-buttons">
                                            @if(auth()->user()?->hasPermission('team.manage-members'))
                                                <a href="{{ route('teams.members', $team) }}" title="{{ __('Members') }}"><i class="icon-people"></i></a>
                                            @endif
                                            @if(auth()->user()?->hasPermission('team.update'))
                                                <a href="{{ route('teams.edit', $team) }}" title="{{ __('Edit') }}"><i class="icon-pencil"></i></a>
                                            @endif
                                            @if(auth()->user()?->hasPermission('team.delete'))
                                                <form method="POST" action="{{ route('teams.destroy', $team) }}" onsubmit="return confirm('Delete this team?');" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" title="{{ __('Delete') }}"><i class="icon-trash"></i></button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="text-center">{{ __('No teams found.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $teams->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

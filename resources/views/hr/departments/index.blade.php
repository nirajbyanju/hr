@extends('layouts.backend')

@section('content')
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center">
        <h1><i class="icon-organization"></i> {{ __('Departments') }}</h1>
        @if(auth()->user()?->hasPermission('department.create'))
            <a href="{{ route('departments.create') }}" class="btn btn-custom"><i class="icon-plus"></i> {{ __('Add Department') }}</a>
        @endif
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            <div class="card no-border">
                <div class="content_wrapper content-padded">
                    <form method="GET" class="row g-2 mb-3">
                        <div class="col-md-4">
                            <input type="text" name="q" value="{{ $filters['q'] }}" class="form-control" placeholder="{{ __('Search name/code/description') }}">
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
                                    <option value="{{ $size }}" {{ (int) $filters['per_page'] === $size ? 'selected' : '' }}>{{ $size }} / page</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 d-flex gap-2">
                            <button class="btn btn-custom" type="submit"><i class="icon-magnifier"></i> {{ __('Filter') }}</button>
                                <a href="{{ route('departments.index') }}" class="btn btn-custom-default"><i class="icon-refresh"></i> {{ __('Reset') }}</a>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>{{ __('Name') }}</th>
                                    <th>{{ __('Code') }}</th>
                                    <th>{{ __('Head') }}</th>
                                    <th>{{ __('Employees') }}</th>
                                    <th>{{ __('Designations') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($departments as $department)
                                    <tr>
                                        <td>{{ $department->name }}</td>
                                        <td>{{ $department->code ?: '-' }}</td>
                                        <td>
                                            @if($department->head)
                                                {{ trim($department->head->first_name.' '.$department->head->last_name) }}
                                                <div class="small text-muted">{{ $department->head->employee_code }}</div>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>{{ $department->employees_count }}</td>
                                        <td>{{ $department->designations_count }}</td>
                                        <td>
                                            @if($department->is_active)
                                                <span class="badge bg-success">{{ __('Active') }}</span>
                                            @else
                                                <span class="badge bg-secondary">{{ __('Inactive') }}</span>
                                            @endif
                                        </td>
                                        <td class="action-buttons">
                                            @if(auth()->user()?->hasPermission('department.update'))
                                                <a href="{{ route('departments.edit', $department) }}" title="{{ __('Edit Department') }}">
                                                    <i class="icon-pencil"></i>
                                                </a>
                                            @endif
                                            @if(auth()->user()?->hasPermission('department.delete'))
                                                <form method="POST" action="{{ route('departments.destroy', $department) }}" onsubmit="return confirm('Delete this department?');" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" title="{{ __('Delete Department') }}"><i class="icon-trash"></i></button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center">{{ __('No departments found.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            </table>
                    </div>

                    {{ $departments->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

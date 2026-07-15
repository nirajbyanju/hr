@extends('layouts.backend')
@section('title', 'Leave Categories')

@section('content')
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center">
        <h1><i class="icon-layers"></i> {{ __('Leave Categories') }}</h1>
        @if(auth()->user()?->hasPermission('leave.manage-categories'))
            <a href="{{ route('leave-categories.create') }}" class="btn btn-custom"><i class="icon-plus"></i> {{ __('Add Leave Category') }}</a>
        @endif
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            <div class="card no-border">
                <div class="content_wrapper content-padded">
                    <form method="GET" class="row g-2 mb-3">
                        <div class="col-md-4">
                            <input type="text" name="q" value="{{ $filters['q'] }}" class="form-control" placeholder="{{ __('Search by name/code/description') }}">
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
                            <a href="{{ route('leave-categories.index') }}" class="btn btn-custom-default"><i class="icon-refresh"></i> {{ __('Reset') }}</a>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>{{ __('Name') }}</th>
                                    <th>{{ __('Code') }}</th>
                                    <th>{{ __('Paid') }}</th>
                                    <th>{{ __('Attachment Required') }}</th>
                                    <th>{{ __('Max Consecutive') }}</th>
                                    <th>{{ __('Policies') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($categories as $category)
                                    <tr>
                                        <td>{{ $category->name }}</td>
                                        <td>{{ $category->code }}</td>
                                        <td>{{ $category->is_paid ? 'Yes' : 'No' }}</td>
                                        <td>{{ $category->requires_attachment ? 'Yes' : 'No' }}</td>
                                        <td>{{ $category->max_consecutive_days ?: '-' }}</td>
                                        <td>{{ (int) $category->policies_count }}</td>
                                        <td>
                                            @if($category->is_active)
                                                <span class="badge bg-success">{{ __('Active') }}</span>
                                            @else
                                                <span class="badge bg-secondary">{{ __('Inactive') }}</span>
                                            @endif
                                        </td>
                                        <td class="action-buttons">
                                            @if(auth()->user()?->hasPermission('leave.manage-categories'))
                                                <a href="{{ route('leave-categories.edit', $category) }}" title="{{ __('Edit') }}">
                                                    <i class="icon-pencil"></i>
                                                </a>
                                                <form method="POST" action="{{ route('leave-categories.destroy', $category) }}" onsubmit="return confirm('Delete this leave category?');" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" title="{{ __('Delete') }}"><i class="icon-trash"></i></button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center">{{ __('No leave categories found.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $categories->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

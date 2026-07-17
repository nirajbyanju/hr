@extends('layouts.backend')

@section('content')
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center">
        <h1><i class="icon-key"></i> {{ __('Permissions') }}</h1>
        @if(auth()->user()?->hasPermission('role.update'))
            <a href="{{ route('permissions.create') }}" class="btn btn-custom"><i class="icon-plus"></i> {{ __('Add Permission') }}</a>
        @endif
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            <div>
                <div class="content_wrapper content-padded">
                    <form method="GET" class="row g-2 mb-3">
                        <div class="col-md-3">
                            <input type="text" name="q" class="form-control" value="{{ $filters['q'] }}" placeholder="{{ __('Search permission') }}">
                        </div>
                        <div class="col-md-3">
                            <select name="group_name" class="form-control">
                                <option value="">{{ __('All Groups') }}</option>
                                @foreach($groups as $group)
                                    <option value="{{ $group }}" {{ $filters['group_name'] === $group ? 'selected' : '' }}>{{ str_replace('_', ' ', $group) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="per_page" class="form-control">
                                @foreach([10,25,50,100] as $size)
                                    <option value="{{ $size }}" {{ (int) $filters['per_page'] === $size ? 'selected' : '' }}>{{ $size }} / page</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 d-flex gap-2">
                            <button class="btn btn-custom" type="submit"><i class="icon-magnifier"></i> {{ __('Filter') }}</button>
                            <a href="{{ route('permissions.index') }}" class="btn btn-custom-default"><i class="icon-refresh"></i> {{ __('Reset') }}</a>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>{{ __('Group') }}</th>
                                    <th>{{ __('Name') }}</th>
                                    <th>{{ __('Access Scope') }}</th>
                                    <th>{{ __('Slug') }}</th>
                                    <th>{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($permissions as $permission)
                                    @php($accessScope = $permission->accessScopeMeta())
                                    <tr>
                                        <td>{{ str_replace('_', ' ', $permission->group_name) }}</td>
                                        <td>{{ $permission->name }}</td>
                                        <td>
                                            <span class="badge {{ $accessScope['badge_class'] }}" title="{{ $accessScope['description'] }}">{{ $accessScope['label'] }}</span>
                                        </td>
                                        <td>{{ $permission->slug }}</td>
                                        <td class="action-buttons">
                                            @if(auth()->user()?->hasPermission('role.update'))
                                                <a href="{{ route('permissions.edit', $permission) }}" title="{{ __('Edit Permission') }}">
                                                    <i class="icon-pencil"></i>
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center">{{ __('No permissions found.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $permissions->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

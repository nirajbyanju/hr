@extends('layouts.backend')

@section('content')
<div class="wrapper-page">
    <div class="page-title">
        <h1><i class="icon-shield"></i> Role Permissions: {{ $role->name }}</h1>
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            <div>
                <div class="content_wrapper content-padded">
                    <div class="row g-2 mb-3">
                        @foreach($permissionScopeLegend as $scope)
                            <div class="col-md-3">
                                <div class="p-2 h-100 role-scope-card">
                                    <span class="badge {{ $scope->access_scope_badge_class }}">{{ $scope->access_scope_label }}</span>
                                    <div class="text-muted mt-2 role-scope-description">{{ $scope->access_scope_description }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <form method="POST" action="{{ route('roles.permissions.sync', $role) }}">
                        @csrf

                        @foreach($permissionsByGroup as $group => $permissions)
                            <div class="mb-3 p-2 role-permission-group">
                                <h6 class="mb-2 text-uppercase">{{ str_replace('_', ' ', $group) }}</h6>
                                <div class="row">
                                    @foreach($permissions as $permission)
                                        <div class="col-md-3 mb-2">
                                            @php($checkboxId = 'permission_'.$role->id.'_'.$permission->id)
                                            @php($accessScope = $permission->accessScopeMeta())
                                            <div class="checkbox checkbox-default">
                                                <input id="{{ $checkboxId }}" type="checkbox" name="permission_ids[]" value="{{ $permission->id }}" {{ in_array($permission->id, $selectedPermissionIds, true) ? 'checked' : '' }}>
                                                <label for="{{ $checkboxId }}">
                                                    {{ $permission->name }}
                                                    <span class="badge {{ $accessScope['badge_class'] }} ms-1" title="{{ $accessScope['description'] }}">{{ $accessScope['label'] }}</span>
                                                </label>
                                            </div>
                                            <div class="text-muted role-permission-slug">{{ $permission->slug }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach

                        <button class="btn btn-custom" type="submit"><i class="icon-check"></i> {{ __('Save Permissions') }}</button>
                        <a href="{{ route('roles.index') }}" class="btn btn-custom-default"><i class="icon-arrow-left"></i> {{ __('Back') }}</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@extends('layouts.backend')

@section('content')
<div class="wrapper-page">
    <div class="page-title">
        <h1><i class="icon-lock"></i> {{ $mode === 'edit' ? __('Edit Role') : __('Add Role') }}</h1>
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            <div>
                <div class="content_wrapper content-padded">
                    <form method="POST" action="{{ $mode === 'edit' ? route('roles.update', $role) : route('roles.store') }}">
                        @csrf
                        @if($mode === 'edit')
                            @method('PUT')
                        @endif

                        <div class="form-group mb-3">
                            <label>{{ __('Role Name') }}</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', $role->name ?? '') }}" required>
                        </div>

                        <div class="form-group mb-3">
                            <label>{{ __('Description') }}</label>
                            <input type="text" name="description" class="form-control" value="{{ old('description', $role->description ?? '') }}">
                        </div>

                        <button class="btn btn-custom" type="submit">
                            <i class="{{ $mode === 'edit' ? 'icon-check' : 'icon-plus' }}"></i>
                            {{ $mode === 'edit' ? __('Update Role') : __('Create Role') }}
                        </button>
                        <a href="{{ route('roles.index') }}" class="btn btn-custom-default"><i class="icon-arrow-left"></i> {{ __('Back') }}</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@extends('layouts.backend')

@section('content')
<div class="wrapper-page">
    <div class="page-title">
        <h1><i class="icon-badge"></i> {{ $mode === 'edit' ? __('Edit Designation') : __('Add Designation') }}</h1>
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            <div class="card no-border">
                <div class="content_wrapper content-padded">
                    <form method="POST" action="{{ $mode === 'edit' ? route('designations.update', $designation) : route('designations.store') }}">
                        @csrf
                        @if($mode === 'edit')
                            @method('PUT')
                        @endif

                        <div class="row">
                            <div class="col-md-6 form-group mb-3">
                                <label>{{ __('Designation Name') }}</label>
                                <input type="text" name="name" class="form-control" value="{{ old('name', $designation->name ?? '') }}" required>
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label>{{ __('Code') }}</label>
                                <input type="text" name="code" class="form-control" value="{{ old('code', $designation->code ?? '') }}" maxlength="30">
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label>{{ __('Department') }}</label>
                                <select name="department_id" class="form-control">
                                    <option value="">{{ __('Select Department') }}</option>
                                    @php($selectedDepartment = old('department_id', $designation->department_id ?? null))
                                    @foreach($departments as $department)
                                        <option value="{{ $department->id }}" {{ (string) $selectedDepartment === (string) $department->id ? 'selected' : '' }}>
                                            {{ $department->name }}{{ $department->code ? ' ('.$department->code.')' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label>{{ __('Status') }}</label>
                                @php($isActive = (int) old('is_active', isset($designation) ? (int) $designation->is_active : 1))
                                <select name="is_active" class="form-control" required>
                                    <option value="1" {{ $isActive === 1 ? 'selected' : '' }}>{{ __('Active') }}</option>
                                    <option value="0" {{ $isActive === 0 ? 'selected' : '' }}>{{ __('Inactive') }}</option>
                                </select>
                            </div>

                            <div class="col-md-12 form-group mb-3">
                                <label>{{ __('Description') }}</label>
                                <textarea name="description" class="form-control" rows="3">{{ old('description', $designation->description ?? '') }}</textarea>
                            </div>
                        </div>

                        <button class="btn btn-custom" type="submit">
                            <i class="{{ $mode === 'edit' ? 'icon-check' : 'icon-plus' }}"></i>
                            {{ $mode === 'edit' ? __('Update Designation') : __('Create Designation') }}
                        </button>
                        <a href="{{ route('designations.index') }}" class="btn btn-custom-default"><i class="icon-arrow-left"></i> {{ __('Back') }}</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

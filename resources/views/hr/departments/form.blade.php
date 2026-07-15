@extends('layouts.backend')

@section('content')
<div class="wrapper-page">
    <div class="page-title">
        <h1><i class="icon-organization"></i> {{ $mode === 'edit' ? __('Edit Department') : __('Add Department') }}</h1>
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            <div class="card no-border">
                <div class="content_wrapper content-padded">
                    <form method="POST" action="{{ $mode === 'edit' ? route('departments.update', $department) : route('departments.store') }}">
                        @csrf
                        @if($mode === 'edit')
                            @method('PUT')
                        @endif

                        <div class="row">
                            <div class="col-md-6 form-group mb-3">
                                <label>{{ __('Name') }}</label>
                                <input type="text" name="name" class="form-control" value="{{ old('name', $department->name ?? '') }}" required>
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label>{{ __('Code') }}</label>
                                <input type="text" name="code" class="form-control" value="{{ old('code', $department->code ?? '') }}" maxlength="30">
                            </div>


                            <div class="col-md-6 form-group mb-3">
                                <label>{{ __('Department Head') }}</label>
                                <select name="head_employee_id" class="form-control">
                                    <option value="">{{ __('Select Head') }}</option>
                                    @php($selectedHead = old('head_employee_id', $department->head_employee_id ?? null))
                                    @foreach($headCandidates as $employee)
                                        <option value="{{ $employee->id }}" {{ (string) $selectedHead === (string) $employee->id ? 'selected' : '' }}>
                                            {{ trim($employee->first_name.' '.$employee->last_name) }} ({{ $employee->employee_code }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>



                            <div class="col-md-6 form-group mb-3">
                                <label>{{ __('Status') }}</label>
                                @php($isActive = (int) old('is_active', isset($department) ? (int) $department->is_active : 1))
                                <select name="is_active" class="form-control" required>
                                    <option value="1" {{ $isActive === 1 ? 'selected' : '' }}>{{ __('Active') }}</option>
                                    <option value="0" {{ $isActive === 0 ? 'selected' : '' }}>{{ __('Inactive') }}</option>
                                </select>
                            </div>

                                    <div class="col-md-12 form-group mb-3">
                                        <label>{{ __('Description') }}</label>
                                        <textarea name="description" class="form-control" rows="3">{{ old('description', $department->description ?? '') }}</textarea>
                                    </div>
                        </div>

                                <button class="btn btn-custom" type="submit">
                                    <i class="{{ $mode === 'edit' ? 'icon-check' : 'icon-plus' }}"></i>
                                    {{ $mode === 'edit' ? __('Update Department') : __('Create Department') }}
                                </button>
                        <a href="{{ route('departments.index') }}" class="btn btn-custom-default"><i class="icon-arrow-left"></i> {{ __('Back') }}</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

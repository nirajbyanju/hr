@extends('layouts.backend')

@section('content')
<div class="wrapper-page">
    <div class="page-title">
        <h1><i class="icon-wallet"></i> {{ $mode === 'edit' ? __('Edit Salary Grade') : __('Add Salary Grade') }}</h1>
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            <div class="card no-border">
                <div class="content_wrapper content-padded">
                    <form method="POST" action="{{ $mode === 'edit' ? route('salary-grades.update', $salaryGrade) : route('salary-grades.store') }}">
                        @csrf
                        @if($mode === 'edit')
                            @method('PUT')
                        @endif

                        <div class="row">
                            <div class="col-md-6 form-group mb-3">
                                <label>{{ __('Grade Name') }}</label>
                                <input type="text" name="grade_name" class="form-control" value="{{ old('grade_name', $salaryGrade->grade_name ?? '') }}" maxlength="60" required>
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label>{{ __('Grade Code') }}</label>
                                <input type="text" name="grade_code" class="form-control" value="{{ old('grade_code', $salaryGrade->grade_code ?? '') }}" maxlength="30" required>
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label>{{ __('Band Name') }}</label>
                                <input type="text" name="band_name" class="form-control" value="{{ old('band_name', $salaryGrade->band_name ?? '') }}" maxlength="60">
                            </div>

                            <div class="col-md-3 form-group mb-3">
                                <label>{{ __('Min Salary') }}</label>
                                <input type="number" step="0.01" min="0" name="min_salary" class="form-control" value="{{ old('min_salary', $salaryGrade->min_salary ?? '') }}">
                            </div>

                            <div class="col-md-3 form-group mb-3">
                                <label>{{ __('Max Salary') }}</label>
                                <input type="number" step="0.01" min="0" name="max_salary" class="form-control" value="{{ old('max_salary', $salaryGrade->max_salary ?? '') }}">
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label>{{ __('Status') }}</label>
                                @php($isActive = (int) old('is_active', isset($salaryGrade) ? (int) $salaryGrade->is_active : 1))
                                <select name="is_active" class="form-control" required>
                                    <option value="1" {{ $isActive === 1 ? 'selected' : '' }}>{{ __('Active') }}</option>
                                    <option value="0" {{ $isActive === 0 ? 'selected' : '' }}>{{ __('Inactive') }}</option>
                                </select>
                            </div>

                            <div class="col-md-12 form-group mb-3">
                                <label>{{ __('Description') }}</label>
                                <textarea name="description" class="form-control" rows="3">{{ old('description', $salaryGrade->description ?? '') }}</textarea>
                            </div>
                        </div>

                        <button class="btn btn-custom" type="submit">
                            <i class="{{ $mode === 'edit' ? 'icon-check' : 'icon-plus' }}"></i>
                            {{ $mode === 'edit' ? __('Update Salary Grade') : __('Create Salary Grade') }}
                        </button>
                        <a href="{{ route('salary-grades.index') }}" class="btn btn-custom-default"><i class="icon-arrow-left"></i> {{ __('Back') }}</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

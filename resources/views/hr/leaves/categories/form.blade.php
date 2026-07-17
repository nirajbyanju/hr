@extends('layouts.backend')
@section('title', $mode === 'edit' ? __('Edit Leave Category') : __('Add Leave Category'))

@section('content')
<div class="wrapper-page">
    <div class="page-title">
        <h1><i class="icon-layers"></i> {{ $mode === 'edit' ? __('Edit Leave Category') : __('Add Leave Category') }}</h1>
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            <div>
                <div class="content_wrapper content-padded">
                    <form method="POST" action="{{ $mode === 'edit' ? route('leave-categories.update', $leaveCategory) : route('leave-categories.store') }}">
                        @csrf
                        @if($mode === 'edit')
                            @method('PUT')
                        @endif

                        <div class="row">
                            <div class="col-md-6 form-group mb-3">
                                <label>{{ __('Name') }}</label>
                                <input type="text" name="name" class="form-control" value="{{ old('name', $leaveCategory->name ?? '') }}" maxlength="120" required>
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label>{{ __('Code') }}</label>
                                <input type="text" name="code" class="form-control" value="{{ old('code', $leaveCategory->code ?? '') }}" maxlength="30" required>
                            </div>

                            <div class="col-md-4 form-group mb-3">
                                <label>{{ __('Paid Leave') }}</label>
                                @php($isPaid = (int) old('is_paid', isset($leaveCategory) ? (int) $leaveCategory->is_paid : 1))
                                <select name="is_paid" class="form-control" required>
                                    <option value="1" {{ $isPaid === 1 ? 'selected' : '' }}>{{ __('Yes') }}</option>
                                    <option value="0" {{ $isPaid === 0 ? 'selected' : '' }}>{{ __('No') }}</option>
                                </select>
                            </div>

                            <div class="col-md-4 form-group mb-3">
                                <label>{{ __('Attachment Required') }}</label>
                                @php($requiresAttachment = (int) old('requires_attachment', isset($leaveCategory) ? (int) $leaveCategory->requires_attachment : 0))
                                <select name="requires_attachment" class="form-control" required>
                                    <option value="0" {{ $requiresAttachment === 0 ? 'selected' : '' }}>{{ __('No') }}</option>
                                    <option value="1" {{ $requiresAttachment === 1 ? 'selected' : '' }}>{{ __('Yes') }}</option>
                                </select>
                            </div>

                            <div class="col-md-4 form-group mb-3">
                                <label>{{ __('Max Consecutive Days') }}</label>
                                <input type="number" min="1" max="255" name="max_consecutive_days" class="form-control" value="{{ old('max_consecutive_days', $leaveCategory->max_consecutive_days ?? '') }}">
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label>{{ __('Status') }}</label>
                                @php($isActive = (int) old('is_active', isset($leaveCategory) ? (int) $leaveCategory->is_active : 1))
                                <select name="is_active" class="form-control" required>
                                    <option value="1" {{ $isActive === 1 ? 'selected' : '' }}>{{ __('Active') }}</option>
                                    <option value="0" {{ $isActive === 0 ? 'selected' : '' }}>{{ __('Inactive') }}</option>
                                </select>
                            </div>

                            <div class="col-md-12 form-group mb-3">
                                <label>{{ __('Description') }}</label>
                                <textarea name="description" class="form-control" rows="3">{{ old('description', $leaveCategory->description ?? '') }}</textarea>
                            </div>
                        </div>

                        <button class="btn btn-custom" type="submit">
                            <i class="{{ $mode === 'edit' ? 'icon-check' : 'icon-plus' }}"></i>
                            {{ $mode === 'edit' ? __('Update Category') : __('Create Category') }}
                        </button>
                        <a href="{{ route('leave-categories.index') }}" class="btn btn-custom-default"><i class="icon-arrow-left"></i> {{ __('Back') }}</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

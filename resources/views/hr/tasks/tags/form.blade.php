@extends('layouts.backend')
@section('title', $mode === 'create' ? __('Add Task Tag') : __('Edit Task Tag'))

@section('content')
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center">
        <h1><i class="icon-tag"></i> {{ $mode === 'create' ? __('Add Task Tag') : __('Edit Task Tag') }}</h1>
        <a href="{{ route('task-tags.index') }}" class="btn btn-custom-default"><i class="icon-arrow-left"></i> {{ __('Back') }}</a>
    </div>

    @include('partials.flash')

    <div class="page-content"><div class="container-fluid"><div class="card no-border"><div class="content_wrapper content-padded">
        <form method="POST" action="{{ $mode === 'create' ? route('task-tags.store') : route('task-tags.update', $tag) }}" class="row g-2">
            @csrf
            @if($mode === 'edit') @method('PUT') @endif
            <div class="col-md-6"><label>{{ __('Name') }}</label><input type="text" name="name" value="{{ old('name', $tag->name ?? '') }}" class="form-control" required></div>
            <div class="col-md-3"><label>{{ __('Color') }}</label><input type="color" name="color" value="{{ old('color', $tag->color ?? '#6c757d') }}" class="form-control form-control-color"></div>
            <div class="col-md-3"><label>{{ __('Active') }}</label><select name="is_active" class="form-control"><option value="1" {{ old('is_active', $tag->is_active ?? true) ? 'selected' : '' }}>{{ __('Yes') }}</option><option value="0" {{ !old('is_active', $tag->is_active ?? true) ? 'selected' : '' }}>{{ __('No') }}</option></select></div>
            <div class="col-md-12 mt-2"><button type="submit" class="btn btn-custom"><i class="icon-check"></i> {{ __('Save') }}</button></div>
        </form>
    </div></div></div></div>
</div>
@endsection

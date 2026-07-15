@extends('layouts.backend')

@section('content')
<div class="wrapper-page">
    <div class="page-title">
        <h1><i class="icon-plane"></i> {{ $mode === 'edit' ? __('Edit Holiday') : __('Add Holiday') }}</h1>
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            <div class="card no-border">
                <div class="content_wrapper content-padded">
                    <form method="POST" action="{{ $mode === 'edit' ? route('holidays.update', $holiday) : route('holidays.store') }}">
                        @csrf
                        @if($mode === 'edit')
                            @method('PUT')
                        @endif

                        <div class="row">
                            <div class="col-md-6 form-group mb-3">
                                <label>{{ __('Holiday Title') }}</label>
                                <input type="text" name="title" class="form-control" value="{{ old('title', $holiday->title ?? '') }}" maxlength="255" required>
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label>{{ __('Holiday Date') }}</label>
                                <input
                                    type="text"
                                    id="holiday_date"
                                    name="holiday_date"
                                    class="form-control datetimepicker"
                                    placeholder="{{ __('YYYY-MM-DD') }}"
                                    autocomplete="off"
                                    value="{{ old('holiday_date', isset($holiday) ? $holiday->holiday_date?->format('Y-m-d') : $defaultDate) }}"
                                    required
                                >
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label>{{ __('Holiday Type') }}</label>
                                @php($selectedType = old('holiday_type', $holiday->holiday_type ?? 'public'))
                                <select name="holiday_type" class="form-control" required>
                                    @foreach(['public', 'national', 'religious', 'company', 'optional'] as $type)
                                        <option value="{{ $type }}" {{ $selectedType === $type ? 'selected' : '' }}>{{ __(ucfirst($type)) }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label>{{ __('Optional Holiday') }}</label>
                                @php($isOptional = (int) old('is_optional', isset($holiday) ? (int) $holiday->is_optional : 0))
                                <select name="is_optional" class="form-control" required>
                                    <option value="0" {{ $isOptional === 0 ? 'selected' : '' }}>{{ __('No') }}</option>
                                    <option value="1" {{ $isOptional === 1 ? 'selected' : '' }}>{{ __('Yes') }}</option>
                                </select>
                            </div>

                            <div class="col-md-12 form-group mb-3">
                                <label>{{ __('Description') }}</label>
                                <textarea name="description" class="form-control" rows="3" maxlength="2000">{{ old('description', $holiday->description ?? '') }}</textarea>
                            </div>
                        </div>

                        <button class="btn btn-custom" type="submit">
                            <i class="{{ $mode === 'edit' ? 'icon-check' : 'icon-plus' }}"></i>
                            {{ $mode === 'edit' ? __('Update Holiday') : __('Create Holiday') }}
                        </button>
                        <a href="{{ route('holidays.index') }}" class="btn btn-custom-default"><i class="icon-arrow-left"></i> {{ __('Back') }}</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const $holidayDate = $('#holiday_date');
        if (!$holidayDate.length || !$.fn.datepicker) {
            return;
        }

        $holidayDate.datepicker('destroy').datepicker({
            format: 'yyyy-mm-dd',
            autoclose: true,
            todayHighlight: true
        });
    })();
</script>
@endpush

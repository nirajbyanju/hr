@props([
    'name',
    'value' => null,
    'label' => null,
    'required' => false,
    'placeholder' => null,
    'id' => null,
    'help' => null,
    'wrapperClass' => 'form-group',
])

@php
    use App\Support\DateSystem;

    $fieldId = $id ?? 'date-field-' . \Illuminate\Support\Str::slug($name, '_') . '-' . \Illuminate\Support\Str::random(4);

    // old() wins so a failed validation round-trip keeps what the user typed.
    // The canonical value is ALWAYS the AD date — the visible field below is a
    // display mirror of it, never the source of truth.
    $adValue = old($name, $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value);
    $adValue = $adValue ? \Illuminate\Support\Str::substr((string) $adValue, 0, 10) : '';

    $isNepali = DateSystem::isNepali();
    $shownValue = $adValue === '' ? '' : ($isNepali ? (DateSystem::toBs($adValue) ?? $adValue) : $adValue);
    $hint = $placeholder ?? ($isNepali ? __('YYYY-MM-DD (B.S.)') : __('YYYY-MM-DD'));

    // $errors is only shared by ShareErrorsFromSession, so it is absent when a
    // view is rendered outside a web request — which this app does for real
    // (dompdf ID cards). Fall back to an empty bag rather than fataling.
    $fieldErrors = $errors ?? new \Illuminate\Support\ViewErrorBag();
@endphp

<div class="{{ $wrapperClass }}">
    @if($label)
        <label for="{{ $fieldId }}">
            {{ $label }}@if($required)<span class="text-danger">*</span>@endif
        </label>
    @endif

    <div class="date-field" data-date-field data-system="{{ $isNepali ? 'bs' : 'ad' }}">
        {{-- What the user sees and the picker writes into. Never submitted. --}}
        <input
            type="text"
            id="{{ $fieldId }}"
            class="form-control date-field__display {{ $fieldErrors->has($name) ? 'is-invalid' : '' }}"
            value="{{ $shownValue }}"
            placeholder="{{ $hint }}"
            autocomplete="off"
            data-date-display
            {{ $required ? 'required' : '' }}
            {{ $attributes->except(['class']) }}
        >

        {{-- The canonical AD value. This is what the controller receives, so no
             validation rule, service or query anywhere needs to know which
             calendar the company picked. --}}
        <input type="hidden" name="{{ $name }}" value="{{ $adValue }}" data-date-value>

        <button type="button" class="date-field__toggle" data-date-toggle
                aria-label="{{ __('Open calendar') }}" tabindex="-1">&#128197;</button>
    </div>

    @if($isNepali)
        <small class="date-field__note" data-date-mirror>
            {{ $adValue === '' ? '' : __('A.D.') . ' ' . $adValue }}
        </small>
    @endif

    @if($help)
        <small class="form-text text-muted">{{ $help }}</small>
    @endif

    @if($fieldErrors->has($name))
        <div class="invalid-feedback d-block">{{ $fieldErrors->first($name) }}</div>
    @endif
</div>

{{--
    Date field for the platform console.

    Emits the same DOM contract as the tenant <x-date-field> component
    (.date-field / data-date-display / data-date-value / data-date-toggle), so
    the shared public/assets/js/date-field.js drives both surfaces and there is
    only one picker in the system to maintain.

    The one difference is the calendar: Bikram Sambat is a per-tenant setting
    read from that tenant's system_settings, and the landlord console has no
    tenant, so it is always Gregorian.

    Variables:
      $name        submitted field name, e.g. 'expires_on'
      $label       field label
      $value       current value in Y-m-d, or null
      $help        optional help text below the field
      $placeholder optional text shown while empty
      $minFrom     optional name of another date field this one cannot precede,
                   or the literal 'today'
      $presets     optional [['label' => '1 year', 'months' => 12], ...] chips
--}}
@php
    $dpValue = old($name, $value ?? null);
    $dpValue = $dpValue ? \Illuminate\Support\Str::substr((string) $dpValue, 0, 10) : '';
@endphp

<div class="field">
    <span class="lab" id="dp_lab_{{ $name }}">{{ $label }}</span>

    <div class="date-field" data-date-field data-system="ad"
         @isset($minFrom) data-min-from="{{ $minFrom }}" @endisset
         @isset($presets) data-presets="{{ json_encode($presets) }}" @endisset>
        {{-- Display mirror; never submitted. --}}
        <input type="text" class="input date-field__display"
               value="{{ $dpValue }}"
               placeholder="{{ $placeholder ?? 'YYYY-MM-DD' }}"
               autocomplete="off" autocapitalize="none" spellcheck="false"
               aria-labelledby="dp_lab_{{ $name }}"
               data-date-display>

        {{-- The canonical value the controller receives. --}}
        <input type="hidden" name="{{ $name }}" value="{{ $dpValue }}" data-date-value>

        <button type="button" class="date-field__toggle" data-date-toggle
                aria-label="Open calendar" tabindex="-1">&#128197;</button>
    </div>

    @isset($help)<div class="help">{{ $help }}</div>@endisset
    @error($name)<div class="err">{{ $message }}</div>@enderror
</div>

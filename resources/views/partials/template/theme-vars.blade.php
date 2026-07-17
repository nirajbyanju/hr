@php
    $primaryColor = \App\Models\SystemSetting::getValue('primary_color') ?: '#0f8f8c';
    $secondaryColor = \App\Models\SystemSetting::getValue('secondary_color') ?: '#25364d';

    $toRgbTriplet = function (string $hex): string {
        $hex = ltrim($hex, '#');
        if (! preg_match('/^[0-9A-Fa-f]{6}$/', $hex)) {
            $hex = '0f8f8c';
        }

        return implode(', ', array_map('hexdec', str_split($hex, 2)));
    };

    $primaryRgb = $toRgbTriplet($primaryColor);
    $secondaryRgb = $toRgbTriplet($secondaryColor);
@endphp
<style id="app-theme-vars">
    :root {
        --hr-accent: {{ $primaryColor }};
        --hr-accent-rgb: {{ $primaryRgb }};
        --hr-accent-soft: color-mix(in srgb, {{ $primaryColor }} 12%, white);
        --hr-accent-hover: color-mix(in srgb, {{ $primaryColor }} 85%, black);

        --hr-primary: {{ $secondaryColor }};
        --hr-primary-rgb: {{ $secondaryRgb }};
        --hr-primary-strong: color-mix(in srgb, {{ $secondaryColor }} 82%, black);
        --hr-text: {{ $secondaryColor }};

        --bs-primary: {{ $primaryColor }};
        --bs-primary-rgb: {{ $primaryRgb }};
    }
</style>

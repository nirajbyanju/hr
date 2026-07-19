<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('ID Card') }} — {{ $employee->employee_code }}</title>
    <style>
        @page { size: 54mm 85.6mm; margin: 0; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body { margin: 0; background: #eceff0; font-family: 'Segoe UI', Arial, sans-serif; }
        .toolbar { text-align: center; padding: 18px; }
        .toolbar button, .toolbar a {
            display: inline-block; padding: 9px 18px; margin: 0 4px; border-radius: 8px;
            font-size: 14px; font-weight: 600; text-decoration: none; cursor: pointer; border: 1px solid #0f766e;
        }
        .toolbar button { background: #0f766e; color: #fff; }
        .toolbar a { background: #fff; color: #0f766e; }
        .stage { display: flex; justify-content: center; padding: 24px; }
        @media print {
            .toolbar { display: none; }
            body { background: #fff; }
            .stage { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">{{ __('Print') }}</button>
        <a href="{{ route('id-cards.preview', $card) }}">{{ __('Back') }}</a>
    </div>
    <div class="stage">
        @include('hr.id-cards.partials.card')
    </div>
    <script>
        window.addEventListener('load', function () {
            setTimeout(function () { window.print(); }, 350);
        });
    </script>
</body>
</html>

@php
    $fullName = trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? ''));
    $initials = strtoupper(mb_substr($employee->first_name ?? 'E', 0, 1) . mb_substr($employee->last_name ?? '', 0, 1));
    $designation = $employee->designation->name ?? '—';
    $department = $employee->department->name ?? '—';
    $blood = $employee->blood_group ?: '—';
    $joined = $employee->date_of_joining ? \Illuminate\Support\Carbon::parse($employee->date_of_joining)->format('d M Y') : '—';
    $phone = $employee->phone ?: '—';
    $accent = '#0f766e';
@endphp
<div style="width:54mm; box-sizing:border-box; border:1px solid #d7dde0; border-radius:3mm; overflow:hidden; background:#ffffff; font-family:'Segoe UI', Arial, sans-serif; color:#14181d;">
    <div style="background:{{ $accent }}; color:#ffffff; text-align:center; padding:2.4mm 2mm;">
        <div style="font-size:11pt; font-weight:700; letter-spacing:0.3pt;">{{ $brandName }}</div>
        <div style="font-size:6pt; letter-spacing:1.2pt; text-transform:uppercase; opacity:0.85;">Employee ID Card</div>
    </div>

    <div style="text-align:center; padding:3mm 3mm 0 3mm;">
        @if($photo)
            <img src="{{ $photo }}" alt="{{ $fullName }}" style="width:23mm; height:23mm; border-radius:2mm; border:1.5pt solid {{ $accent }}; object-fit:cover;">
        @else
            <div style="width:23mm; height:23mm; border-radius:2mm; border:1.5pt solid {{ $accent }}; background:#e2f1ef; color:{{ $accent }}; display:inline-block; text-align:center; line-height:23mm; font-size:16pt; font-weight:700;">{{ $initials }}</div>
        @endif
        <div style="font-size:11pt; font-weight:700; margin-top:2mm; line-height:1.15;">{{ $fullName }}</div>
        <div style="font-size:7.5pt; color:{{ $accent }}; font-weight:600; margin-top:0.6mm;">{{ $designation }}</div>
        <div style="font-size:7pt; color:#62777a; margin-top:0.3mm;">{{ $department }}</div>
    </div>

    <table style="width:100%; border-collapse:collapse; margin-top:2.5mm; padding:0 3mm; font-size:7pt;">
        <tr>
            <td style="padding:0.8mm 3mm; color:#8a959b; text-transform:uppercase; letter-spacing:0.4pt;">ID</td>
            <td style="padding:0.8mm 3mm; text-align:right; font-weight:700;">{{ $employee->employee_code }}</td>
        </tr>
        <tr style="background:#f6f8f8;">
            <td style="padding:0.8mm 3mm; color:#8a959b; text-transform:uppercase; letter-spacing:0.4pt;">Blood</td>
            <td style="padding:0.8mm 3mm; text-align:right; font-weight:700;">{{ $blood }}</td>
        </tr>
        <tr>
            <td style="padding:0.8mm 3mm; color:#8a959b; text-transform:uppercase; letter-spacing:0.4pt;">Joined</td>
            <td style="padding:0.8mm 3mm; text-align:right; font-weight:700;">{{ $joined }}</td>
        </tr>
        <tr style="background:#f6f8f8;">
            <td style="padding:0.8mm 3mm; color:#8a959b; text-transform:uppercase; letter-spacing:0.4pt;">Phone</td>
            <td style="padding:0.8mm 3mm; text-align:right; font-weight:700;">{{ $phone }}</td>
        </tr>
    </table>

    <div style="text-align:center; margin-top:2mm;">
        <div style="display:inline-block; padding:1.5mm; border:1px solid #eceff0; border-radius:1.5mm; background:#ffffff;">
            {!! $qrSvg !!}
        </div>
        <div style="font-size:6pt; color:#62777a; margin-top:1mm; letter-spacing:0.3pt;">SCAN TO MARK ATTENDANCE</div>
    </div>

    <div style="background:{{ $accent }}; color:#ffffff; text-align:center; padding:1.6mm 2mm; margin-top:2.5mm; font-size:6pt; letter-spacing:0.4pt;">
        {{ $card->card_number }} · Property of {{ $brandName }}
    </div>
</div>

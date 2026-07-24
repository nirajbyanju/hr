@php
    // Only the methods the administrator enabled in Settings → Attendance
    // Configuration, further narrowed to the ones this user may open.
    $attendanceMethodItems = \App\Support\AttendanceMethods::navItemsFor(auth()->user());
@endphp

@if(! empty($attendanceMethodItems))
    <div class="attendance-method-nav mb-3">
        @foreach($attendanceMethodItems as $item)
            <a href="{{ $item['url'] }}"
               class="attendance-method-tab {{ $item['active'] ? 'active' : '' }}"
               @if($item['active']) aria-current="page" @endif>
                <i class="{{ $item['icon'] }}"></i>
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </div>

    @push('styles')
    <style>
        .attendance-method-nav {
            display: inline-flex;
            gap: 4px;
            padding: 4px;
            background: #f1f3f6;
            border-radius: 10px;
            max-width: 100%;
            overflow-x: auto;
        }
        .attendance-method-tab {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            color: #5b6b7f;
            text-decoration: none;
            white-space: nowrap;
            transition: background 0.15s ease, color 0.15s ease;
        }
        .attendance-method-tab:hover {
            color: var(--hr-accent);
        }
        .attendance-method-tab.active {
            background: #fff;
            color: var(--hr-accent);
            box-shadow: 0 1px 3px rgba(16, 24, 40, 0.12);
        }
    </style>
    @endpush
@endif

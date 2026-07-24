@extends('layouts.backend')
@section('title', 'Leave Applications')

@php
    $today = now()->startOfDay();
    $fallbackColor = \App\Support\LeaveCategoryColor::FALLBACK;
    $dayCount = count($calendarDays);
    $firstDay = $calendarDays[0] ?? null;
    $lastDay = $calendarDays[$dayCount - 1] ?? null;

    // Re-open the form automatically when a submission bounced back with errors,
    // otherwise the messages would be hidden inside a collapsed panel.
    $formOpen = $errors->any() || old('leave_category_id');
@endphp

@section('content')
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h1><i class="icon-calendar"></i> {{ __('Leave Applications') }}</h1>
        <div class="d-flex gap-2">
            @if($canExport && $firstDay && $lastDay)
                <a class="btn btn-custom-default"
                   href="{{ route('leave-reports.export', ['from_date' => $firstDay->format('Y-m-d'), 'to_date' => $lastDay->format('Y-m-d')]) }}">
                    <i class="icon-docs"></i> {{ __('Export') }}
                </a>
            @endif
            @if($employee)
                <button type="button" class="btn btn-custom" id="lv_toggle_form">
                    <i class="icon-plus"></i> {{ __('Add Leave Application') }}
                </button>
            @endif
        </div>
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            @if(! $employee)
                <div class="alert alert-danger">{{ __('Your account is not linked with an employee profile. Contact HR.') }}</div>
            @endif

            {{-- ------------------------------------------------------------------
                 New request form — collapsed by default so the calendar leads.
                 ------------------------------------------------------------------ --}}
            @if($employee)
                <div class="mb-3 {{ $formOpen ? '' : 'd-none' }}" id="lv_form_panel">
                    <div class="content_wrapper content-padded">
                        <h5 class="table_banner_title mb-3">{{ __('New Leave Request') }}</h5>

                        @if($balances->isNotEmpty())
                            <div class="row g-2 mb-3">
                                @foreach($balances as $balance)
                                    <div class="col-6 col-md-3 col-lg-2">
                                        <div class="lv-balance">
                                            <div class="lv-balance-name">{{ $balance->leaveCategory?->name ?? '-' }}</div>
                                            <div class="lv-balance-value">{{ number_format((float) $balance->closing_balance, 2) }}</div>
                                            <div class="lv-balance-unit">{{ __('days available') }}</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if($isAdminApplicant)
                            <div class="alert alert-info">{{ __('As an administrator, your leave request is approved automatically on submission.') }}</div>
                        @elseif(! $hasManager)
                            <div class="alert alert-warning">{{ __('No direct supervisor is set on your profile — your request will be routed to HR for approval.') }}</div>
                        @endif

                        <form method="POST" action="{{ route('leave-applications.store') }}" class="row g-2">
                            @csrf
                            <div class="col-md-3">
                                <label>{{ __('Leave Category') }}</label>
                                <select name="leave_category_id" class="form-control" required>
                                    <option value="">{{ __('Select Category') }}</option>
                                    @foreach($leaveCategories as $category)
                                        <option value="{{ $category->id }}" {{ (int) old('leave_category_id') === (int) $category->id ? 'selected' : '' }}>
                                            {{ $category->name }} ({{ $category->code }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <x-date-field name="start_date" :label="__('Start Date')" wrapper-class="" required />
                            </div>
                            <div class="col-md-2">
                                {{-- Mirrors the after_or_equal:start_date rule on StoreLeaveApplicationRequest. --}}
                                <x-date-field name="end_date" :label="__('End Date')" min-from="start_date" wrapper-class="" required />
                            </div>
                            <div class="col-md-2">
                                <label>{{ __('Half Day') }}</label>
                                @php $isHalfDay = (int) old('is_half_day', 0); @endphp
                                <select name="is_half_day" id="is_half_day" class="form-control" required>
                                    <option value="0" {{ $isHalfDay === 0 ? 'selected' : '' }}>{{ __('No') }}</option>
                                    <option value="1" {{ $isHalfDay === 1 ? 'selected' : '' }}>{{ __('Yes') }}</option>
                                </select>
                            </div>
                            <div class="col-md-3" id="half_day_session_group">
                                <label>{{ __('Half-day Session') }}</label>
                                <select name="half_day_session" class="form-control">
                                    <option value="">{{ __('Select Session') }}</option>
                                    <option value="first_half" {{ old('half_day_session') === 'first_half' ? 'selected' : '' }}>{{ __('First Half') }}</option>
                                    <option value="second_half" {{ old('half_day_session') === 'second_half' ? 'selected' : '' }}>{{ __('Second Half') }}</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label>{{ __('Reason') }}</label>
                                <textarea name="reason" class="form-control" rows="3" required>{{ old('reason') }}</textarea>
                            </div>
                            <div class="col-md-12 mt-2">
                                <button type="submit" class="btn btn-custom"><i class="icon-check"></i> {{ __('Submit Leave Request') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif

            {{-- ------------------------------------------------------------------
                 Month picker + employee filter
                 ------------------------------------------------------------------ --}}
            <div class="content_wrapper content-padded mb-3">
                <form method="GET" class="lv-toolbar" id="lv_calendar_filter">
                    <div class="lv-month-nav">
                        <a class="lv-nav-btn"
                           href="{{ request()->fullUrlWithQuery(['month' => $calendarPrevMonth]) }}"
                           title="{{ __('Previous month') }}"><i class="icon-arrow-left"></i></a>
                        <span class="lv-month-label">{{ $calendarMonth->format('F Y') }}</span>
                        <a class="lv-nav-btn"
                           href="{{ request()->fullUrlWithQuery(['month' => $calendarNextMonth]) }}"
                           title="{{ __('Next month') }}"><i class="icon-arrow-right"></i></a>
                    </div>

                    <div class="lv-employee-filter">
                        <label for="calendar_employee_id">{{ __('Employee') }}</label>
                        <input type="hidden" name="month" value="{{ $calendarMonth->format('Y-m') }}">
                        <select name="calendar_employee_id" id="calendar_employee_id" class="form-control" onchange="this.form.submit()">
                            <option value="0">{{ __('All Employees') }}</option>
                            @foreach($calendarFilterEmployees as $filterEmployee)
                                <option value="{{ $filterEmployee->id }}" {{ (int) $calendarSelectedEmployeeId === (int) $filterEmployee->id ? 'selected' : '' }}>
                                    {{ trim($filterEmployee->first_name.' '.$filterEmployee->last_name) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </form>
            </div>

            {{-- ------------------------------------------------------------------
                 Legend — driven entirely by the leave categories in the database.
                 ------------------------------------------------------------------ --}}
            <div class="content_wrapper content-padded mb-3">
                <div class="lv-legend">
                    <span class="lv-legend-title">{{ __('Legend:') }}</span>
                    <span class="lv-legend-item">
                        <i class="lv-dot lv-dot-today"></i> {{ __('Today') }}
                    </span>
                    @forelse($leaveCategories as $category)
                        <span class="lv-legend-item">
                            <i class="lv-dot" style="--lv-c: {{ $categoryColors[$category->id] ?? $fallbackColor }}"></i>
                            {{ $category->name }}
                        </span>
                    @empty
                        <span class="text-muted">{{ __('No leave categories defined yet.') }}</span>
                    @endforelse
                </div>
            </div>

            {{-- ------------------------------------------------------------------
                 The calendar grid
                 ------------------------------------------------------------------ --}}
            <div class="content_wrapper">
                @if($calendarEmployees->isEmpty())
                    <div class="p-3">
                        <div class="alert alert-info mb-0">{{ __('No employees to show on the calendar.') }}</div>
                    </div>
                @else
                    <div class="lv-calendar">
                        <div class="lv-col-employee">
                            <div class="lv-head-cell lv-head-employee">{{ __('Employee') }}</div>
                            @foreach($calendarEmployees as $calEmployee)
                                <div class="lv-emp-cell">
                                    <img class="lv-emp-avatar"
                                         src="{{ $calEmployee->avatar_path ? asset($calEmployee->avatar_path) : asset(\App\Support\DefaultAvatar::forGender($calEmployee->gender)) }}"
                                         alt="{{ trim($calEmployee->first_name.' '.$calEmployee->last_name) }}">
                                    <span class="lv-emp-text">
                                        <span class="lv-emp-name">{{ trim($calEmployee->first_name.' '.$calEmployee->last_name) }}</span>
                                        <span class="lv-emp-role">{{ $calEmployee->designation?->name ?? __('Unassigned') }}</span>
                                    </span>
                                </div>
                            @endforeach
                        </div>

                        <div class="lv-scroll" id="lv_scroll">
                            <button type="button" class="lv-day-nav lv-day-prev" id="lv_day_prev" title="{{ __('Scroll back') }}">
                                <i class="icon-arrow-left"></i>
                            </button>
                            <button type="button" class="lv-day-nav lv-day-next" id="lv_day_next" title="{{ __('Scroll forward') }}">
                                <i class="icon-arrow-right"></i>
                            </button>

                            <div class="lv-grid" style="--lv-days: {{ $dayCount }}">
                                {{-- day header --}}
                                @foreach($calendarDays as $day)
                                    @php $isToday = $day->isSameDay($today); @endphp
                                    <div class="lv-head-cell lv-day-head {{ $isToday ? 'is-today' : '' }} {{ $day->isWeekend() ? 'is-weekend' : '' }}"
                                         style="grid-column: {{ $loop->iteration }}; grid-row: 1;">
                                        <span class="lv-day-num">{{ $day->format('j') }}</span>
                                        <span class="lv-day-name">{{ $day->format('D') }}</span>
                                    </div>
                                @endforeach

                                {{-- one band per employee: background cells, then the leave bars --}}
                                @foreach($calendarEmployees as $rowIndex => $calEmployee)
                                    @php $gridRow = $rowIndex + 2; @endphp
                                    @foreach($calendarDays as $day)
                                        <div class="lv-cell {{ $day->isSameDay($today) ? 'is-today' : '' }} {{ $day->isWeekend() ? 'is-weekend' : '' }}"
                                             style="grid-column: {{ $loop->iteration }}; grid-row: {{ $gridRow }};"></div>
                                    @endforeach

                                    @foreach($calendarLeaves->get($calEmployee->id, collect()) as $leave)
                                        @php
                                            // Clip to the visible month so leave running in from the
                                            // previous month (or out into the next) still draws.
                                            $start = \Illuminate\Support\Carbon::parse($leave->start_date)->startOfDay();
                                            $end = \Illuminate\Support\Carbon::parse($leave->end_date)->startOfDay();
                                            $clippedStart = $start->lt($firstDay) ? $firstDay : $start;
                                            $clippedEnd = $end->gt($lastDay) ? $lastDay : $end;

                                            $startCol = (int) $firstDay->diffInDays($clippedStart) + 1;
                                            $span = (int) $clippedStart->diffInDays($clippedEnd) + 1;
                                            $color = $categoryColors[$leave->leave_category_id] ?? $fallbackColor;
                                            $runsInFromBefore = $start->lt($firstDay);
                                            $runsOutAfter = $end->gt($lastDay);
                                        @endphp
                                        <div class="lv-bar {{ $leave->status !== 'approved' ? 'is-pending' : '' }} {{ $runsInFromBefore ? 'runs-in' : '' }} {{ $runsOutAfter ? 'runs-out' : '' }}"
                                             style="grid-column: {{ $startCol }} / span {{ $span }}; grid-row: {{ $gridRow }}; --lv-c: {{ $color }};"
                                             title="{{ $leave->leaveCategory?->name }} — {{ $leave->start_date }} → {{ $leave->end_date }} ({{ number_format((float) $leave->total_days, 2) }} {{ __('days') }}, {{ __(ucfirst(str_replace('_', ' ', $leave->status))) }})">
                                            <span class="lv-bar-title">{{ $leave->leaveCategory?->name ?? __('Leave') }}</span>
                                            <span class="lv-bar-sub">
                                                {{ $leave->leaveCategory?->is_paid ? __('Paid Leave') : __('Unpaid Leave') }}
                                                @if($leave->status !== 'approved')
                                                    · {{ __('Pending') }}
                                                @endif
                                            </span>
                                        </div>
                                    @endforeach
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- ------------------------------------------------------------------
                 The applicant's own history, unchanged.
                 ------------------------------------------------------------------ --}}
            <div class="mt-3">
                <div class="content_wrapper content-padded">
                    <h5 class="table_banner_title mb-3">{{ __('My Leave Requests') }}</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>{{ __('Applied At') }}</th>
                                    <th>{{ __('Category') }}</th>
                                    <th>{{ __('Date Range') }}</th>
                                    <th>{{ __('Days') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Approver') }}</th>
                                    <th>{{ __('Remarks') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($applications as $application)
                                    <tr>
                                        <td>{{ $application->created_at?->format('Y-m-d H:i') }}</td>
                                        <td>{{ $application->leaveCategory?->name ?? '-' }}</td>
                                        <td>{{ $application->start_date }} to {{ $application->end_date }}</td>
                                        <td>{{ number_format((float) $application->total_days, 2) }}</td>
                                        <td>
                                        @if($application->status === 'approved')
                                            <span class="badge bg-success">{{ __('Approved') }}</span>
                                        @elseif($application->status === 'rejected')
                                            <span class="badge bg-danger">{{ __('Rejected') }}</span>
                                        @elseif($application->status === 'supervisor_approved')
                                            <span class="badge bg-info text-dark">{{ __('Awaiting HR Approval') }}</span>
                                        @else
                                            <span class="badge bg-warning text-dark">{{ __('Pending') }}</span>
                                        @endif
                                        </td>
                                        <td>{{ $application->approver?->name ?? '-' }}</td>
                                        <td>{{ $application->approval_remarks ?: '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center">{{ __('No leave requests found.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $applications->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .lv-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        margin: 0;
    }
    .lv-month-nav {
        display: inline-flex;
        align-items: center;
        gap: 14px;
    }
    .lv-nav-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border: 1px solid #e3e8ef;
        border-radius: 8px;
        color: #5b6b7f;
        background: #fff;
        text-decoration: none;
        font-size: 12px;
    }
    .lv-nav-btn:hover {
        border-color: var(--hr-accent);
        color: var(--hr-accent);
    }
    .lv-month-label {
        font-size: 15px;
        font-weight: 600;
        color: #25364d;
        min-width: 130px;
        text-align: center;
    }
    .lv-employee-filter {
        display: inline-flex;
        align-items: center;
        gap: 10px;
    }
    .lv-employee-filter label {
        margin: 0;
        font-size: 13px;
        color: #5b6b7f;
    }
    .lv-employee-filter .form-control {
        min-width: 200px;
    }

    .lv-legend {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px 18px;
        font-size: 12.5px;
        color: #5b6b7f;
    }
    .lv-legend-title {
        font-weight: 600;
        color: #25364d;
    }
    .lv-legend-item {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        white-space: nowrap;
    }
    .lv-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: var(--lv-c, #667085);
        flex: none;
    }
    .lv-dot-today {
        background: #fff;
        border: 2px solid var(--hr-accent);
    }

    .lv-balance {
        border: 1px solid #e3e8ef;
        border-radius: 10px;
        padding: 12px 10px;
        text-align: center;
    }
    .lv-balance-name { font-size: 12px; color: #667085; }
    .lv-balance-value { font-size: 22px; font-weight: 700; color: #25364d; }
    .lv-balance-unit { font-size: 11px; color: #98a2b3; }

    /* Calendar: a frozen employee column beside a horizontally scrolling day grid.
       Both use the same row heights so the two halves stay aligned. */
    .lv-calendar {
        display: flex;
        align-items: stretch;
        --lv-row-h: 68px;
        --lv-head-h: 52px;
        --lv-day-w: 132px;
    }
    .lv-col-employee {
        flex: none;
        width: 230px;
        border-right: 1px solid #e3e8ef;
        background: #fff;
        z-index: 2;
    }
    .lv-head-cell {
        height: var(--lv-head-h);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        background: #f8fafc;
        border-bottom: 1px solid #e3e8ef;
        font-size: 12px;
        color: #5b6b7f;
    }
    .lv-head-employee {
        align-items: flex-start;
        justify-content: center;
        padding-left: 16px;
        font-weight: 600;
        color: #25364d;
    }
    .lv-emp-cell {
        height: var(--lv-row-h);
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 0 12px 0 16px;
        border-bottom: 1px solid #eef1f5;
    }
    .lv-emp-avatar {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        object-fit: cover;
        background: #eef1f5;
        flex: none;
    }
    .lv-emp-text {
        display: flex;
        flex-direction: column;
        min-width: 0;
    }
    .lv-emp-name {
        font-size: 13px;
        font-weight: 600;
        color: #25364d;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .lv-emp-role {
        font-size: 11.5px;
        color: #98a2b3;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .lv-scroll {
        position: relative;
        flex: 1 1 auto;
        min-width: 0;
        overflow-x: auto;
        overflow-y: hidden;
    }
    /* Deliberately no scroll-behavior: smooth here. Smooth scrolling — whether
       set in CSS or passed to scrollBy() — is silently dropped in some embedded
       browsers, which leaves the day buttons doing nothing at all. The buttons
       assign scrollLeft directly instead, which always moves the grid. */
    .lv-grid {
        display: grid;
        grid-template-columns: repeat(var(--lv-days), var(--lv-day-w));
        /* Row 1 is the day header and must match the employee column's header
           height exactly; every implicit row after it is one employee band.
           Leaving row 1 to grid-auto-rows would make it a full band tall and
           knock the two halves of the calendar out of alignment. */
        grid-template-rows: var(--lv-head-h);
        grid-auto-rows: var(--lv-row-h);
        min-width: min-content;
    }
    .lv-day-head {
        gap: 2px;
        border-right: 1px solid #eef1f5;
    }
    .lv-day-num {
        font-size: 14px;
        font-weight: 600;
        color: #25364d;
        line-height: 1;
    }
    .lv-day-name {
        font-size: 11px;
        color: #98a2b3;
        line-height: 1;
    }
    .lv-day-head.is-today {
        background: var(--hr-accent-soft);
    }
    .lv-day-head.is-today .lv-day-num,
    .lv-day-head.is-today .lv-day-name {
        color: var(--hr-accent);
    }
    .lv-cell {
        border-right: 1px solid #eef1f5;
        border-bottom: 1px solid #eef1f5;
    }
    .lv-cell.is-weekend,
    .lv-day-head.is-weekend:not(.is-today) {
        background: #fcfcfd;
    }
    .lv-cell.is-today {
        background: var(--hr-accent-soft);
    }

    /* Bars sit in the same grid cells as the background, one row per employee. */
    .lv-bar {
        position: relative;
        z-index: 1;
        margin: 10px 6px;
        padding: 7px 10px;
        border-radius: 8px;
        border: 1px solid var(--lv-c);
        background: color-mix(in srgb, var(--lv-c) 12%, white);
        overflow: hidden;
        cursor: default;
    }
    .lv-bar-title {
        display: block;
        font-size: 12px;
        font-weight: 600;
        color: var(--lv-c);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .lv-bar-sub {
        display: block;
        font-size: 10.5px;
        color: color-mix(in srgb, var(--lv-c) 70%, #475467);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    /* Pending leave is not committed yet — dashed so it reads differently. */
    .lv-bar.is-pending {
        border-style: dashed;
        background: color-mix(in srgb, var(--lv-c) 7%, white);
    }
    /* Square off the edge where the leave continues outside this month. */
    .lv-bar.runs-in {
        border-top-left-radius: 0;
        border-bottom-left-radius: 0;
        border-left-style: dotted;
        margin-left: 0;
    }
    .lv-bar.runs-out {
        border-top-right-radius: 0;
        border-bottom-right-radius: 0;
        border-right-style: dotted;
        margin-right: 0;
    }

    .lv-day-nav {
        position: absolute;
        top: 10px;
        z-index: 3;
        width: 30px;
        height: 30px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        border: 1px solid #e3e8ef;
        border-radius: 8px;
        background: #fff;
        color: #5b6b7f;
        font-size: 11px;
        cursor: pointer;
        box-shadow: 0 1px 4px rgba(16, 24, 40, 0.1);
    }
    .lv-day-nav:hover {
        border-color: var(--hr-accent);
        color: var(--hr-accent);
    }
    .lv-day-prev { left: 8px; }
    .lv-day-next { right: 8px; }
    .lv-day-nav[disabled] {
        opacity: 0.35;
        cursor: default;
    }

    @media (max-width: 767px) {
        .lv-calendar { --lv-day-w: 108px; }
        .lv-col-employee { width: 160px; }
    }
</style>
@endpush

@push('scripts')
<script>
(function () {
    // Half-day session only applies when half-day is chosen.
    var halfDaySelect = document.getElementById('is_half_day');
    var halfDaySessionGroup = document.getElementById('half_day_session_group');
    function toggleHalfDaySession() {
        if (!halfDaySelect || !halfDaySessionGroup) {
            return;
        }

        halfDaySessionGroup.style.display = halfDaySelect.value === '1' ? '' : 'none';
    }

    toggleHalfDaySession();
    if (halfDaySelect) {
        halfDaySelect.addEventListener('change', toggleHalfDaySession);
    }

    var formToggle = document.getElementById('lv_toggle_form');
    var formPanel = document.getElementById('lv_form_panel');
    if (formToggle && formPanel) {
        formToggle.addEventListener('click', function () {
            formPanel.classList.toggle('d-none');
            if (!formPanel.classList.contains('d-none')) {
                formPanel.scrollIntoView({behavior: 'smooth', block: 'nearest'});
            }
        });
    }

    var scroller = document.getElementById('lv_scroll');
    if (!scroller) {
        return;
    }

    var prev = document.getElementById('lv_day_prev');
    var next = document.getElementById('lv_day_next');

    function dayWidth() {
        var grid = scroller.querySelector('.lv-grid');
        var raw = grid ? getComputedStyle(grid).getPropertyValue('grid-template-columns').split(' ')[0] : '';
        var w = parseFloat(raw);
        return isNaN(w) ? 132 : w;
    }

    function syncNavState() {
        var max = scroller.scrollWidth - scroller.clientWidth;
        if (prev) { prev.disabled = scroller.scrollLeft <= 1; }
        if (next) { next.disabled = scroller.scrollLeft >= max - 1; }
    }

    function step(direction) {
        var max = scroller.scrollWidth - scroller.clientWidth;
        var target = scroller.scrollLeft + (direction * dayWidth() * 7);
        scroller.scrollLeft = Math.max(0, Math.min(max, target));
        syncNavState();
    }

    if (prev) { prev.addEventListener('click', function () { step(-1); }); }
    if (next) { next.addEventListener('click', function () { step(1); }); }
    scroller.addEventListener('scroll', syncNavState);

    // Open on today when the month in view contains it, so the useful part of a
    // 31-column grid is on screen without scrolling.
    var todayCell = scroller.querySelector('.lv-day-head.is-today');
    if (todayCell) {
        scroller.scrollLeft = Math.max(0, todayCell.offsetLeft - scroller.clientWidth / 2 + todayCell.offsetWidth / 2);
    }
    syncNavState();
})();
</script>
@endpush

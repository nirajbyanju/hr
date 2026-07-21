@extends('layouts.backend')

@php
    use App\Modules\Attendance\Services\AttendanceCalendarService as Cal;

    // state => [icon html class or text, colour class, label]
    $legend = [
        Cal::STATE_PRESENT   => ['icon' => 'fa fa-check',      'cls' => 'att-present',   'label' => __('Present')],
        Cal::STATE_ABSENT    => ['icon' => 'fa fa-times',      'cls' => 'att-absent',    'label' => __('Absent')],
        Cal::STATE_HALF_DAY  => ['icon' => '½',                'cls' => 'att-half',      'label' => __('Half Day')],
        Cal::STATE_ON_LEAVE  => ['icon' => 'fa fa-flag',       'cls' => 'att-leave',     'label' => __('On Leave')],
        Cal::STATE_HOLIDAY   => ['icon' => 'fa fa-star',       'cls' => 'att-holiday',   'label' => __('Holiday')],
        Cal::STATE_DAY_OFF   => ['icon' => 'fa fa-ban',        'cls' => 'att-dayoff',    'label' => __('Day Off')],
        Cal::STATE_FUTURE    => ['icon' => 'fa fa-circle',     'cls' => 'att-future',    'label' => __('Future')],
        Cal::STATE_NOT_ADDED => ['icon' => 'fa fa-circle-o',   'cls' => 'att-none',      'label' => __('Attendance Not Added')],
    ];
    $subLegend = [
        'late'     => ['icon' => 'fa fa-clock-o',   'cls' => 'sub-late',  'label' => __('Late')],
        'early'    => ['icon' => 'fa fa-arrow-left', 'cls' => 'sub-early', 'label' => __('Early Departure')],
        'overtime' => ['icon' => 'fa fa-clock-o',   'cls' => 'sub-ot',    'label' => __('Overtime')],
    ];
@endphp

@section('content')
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h1><i class="icon-clock"></i> {{ __('Attendance Records') }}</h1>
        <div class="d-flex gap-2">
            @if($canExportAttendance)
                <a href="{{ route('attendance.export', array_filter(['employee_id' => $filters['employee_id'] ?: null, 'from_date' => sprintf('%04d-%02d-01', $filters['year'], $filters['month']), 'to_date' => \Illuminate\Support\Carbon::create($filters['year'], $filters['month'], 1)->endOfMonth()->format('Y-m-d')])) }}" class="btn btn-custom-default btn-sm"><i class="icon-doc"></i> {{ __('Export') }}</a>
            @endif
            @if($canImportAttendance)
                <a href="{{ route('attendance.index') }}#import" class="btn btn-custom-default btn-sm"><i class="icon-cloud-upload"></i> {{ __('Import') }}</a>
            @endif
            @if($canManageAttendance)
                <a href="{{ route('attendance.index') }}#add" class="btn btn-custom btn-sm"><i class="icon-plus"></i> {{ __('Add Record') }}</a>
            @endif
        </div>
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            {{-- Filters --}}
            <div class="content_wrapper content-padded mb-3">
                <form method="GET" id="att-records-filters" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">{{ __('Employee') }}</label>
                        <select name="employee_id" class="form-control js-example-basic-single">
                            <option value="0">{{ __('All Employees') }}</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}" {{ (int) $filters['employee_id'] === $employee->id ? 'selected' : '' }}>
                                    {{ trim($employee->first_name.' '.$employee->last_name) }} ({{ $employee->employee_code }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">{{ __('Month') }}</label>
                        <select name="month" class="form-control">
                            @foreach($months as $num => $name)
                                <option value="{{ $num }}" {{ (int) $filters['month'] === $num ? 'selected' : '' }}>{{ __($name) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">{{ __('Year') }}</label>
                        <select name="year" class="form-control">
                            @foreach($years as $y)
                                <option value="{{ $y }}" {{ (int) $filters['year'] === $y ? 'selected' : '' }}>{{ $y }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">{{ __('Per Page') }}</label>
                        <select name="per_page" class="form-control">
                            @foreach([10, 20, 30, 50] as $size)
                                <option value="{{ $size }}" {{ (int) $filters['per_page'] === $size ? 'selected' : '' }}>{{ $size }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button class="btn btn-custom" type="submit"><i class="icon-magnifier"></i> {{ __('Apply Filters') }}</button>
                        <a href="{{ route('attendance.records') }}" class="btn btn-custom-default"><i class="icon-refresh"></i> {{ __('Reset') }}</a>
                    </div>
                </form>
            </div>

            {{-- Legend --}}
            <div class="content_wrapper content-padded mb-3 att-legend">
                @foreach($legend as $item)
                    <span class="att-legend-item">
                        <span class="att-icon {{ $item['cls'] }}">@if(str_starts_with($item['icon'], 'fa'))<i class="{{ $item['icon'] }}"></i>@else{{ $item['icon'] }}@endif</span>
                        {{ $item['label'] }}
                    </span>
                @endforeach
                @foreach($subLegend as $item)
                    <span class="att-legend-item">
                        <span class="att-sub {{ $item['cls'] }}"><i class="{{ $item['icon'] }}"></i></span>
                        {{ $item['label'] }}
                    </span>
                @endforeach
            </div>

            {{-- Grid --}}
            <div class="content_wrapper content-padded">
                <div class="att-grid-scroll">
                    <table class="att-grid">
                        <thead>
                            <tr>
                                <th class="att-emp-col att-month-head" rowspan="2">{{ __('Employee') }}</th>
                                <th colspan="{{ count($grid['days']) }}" class="att-month-title">
                                    {{ \Illuminate\Support\Carbon::create($grid['year'], $grid['month'], 1)->format('F Y') }}
                                </th>
                                <th class="att-total-col" rowspan="2">{{ __('Total') }}</th>
                            </tr>
                            <tr>
                                @foreach($grid['days'] as $day)
                                    <th class="att-day-head {{ $day['weekend'] ? 'att-weekend' : '' }}">
                                        <span class="att-day-num">{{ $day['day'] }}</span>
                                        <span class="att-day-wd">{{ $day['weekday'] }}</span>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($grid['rows'] as $row)
                                @php($emp = $row['employee'])
                                @php($photo = $emp->avatar_path ? asset($emp->avatar_path) : asset(\App\Support\DefaultAvatar::forGender($emp->gender)))
                                <tr>
                                    <td class="att-emp-col">
                                        <div class="att-emp">
                                            <img src="{{ $photo }}" alt="" class="att-emp-avatar">
                                            <div class="att-emp-meta">
                                                <div class="att-emp-name">{{ trim($emp->first_name.' '.$emp->last_name) }}</div>
                                                <div class="att-emp-role">{{ $emp->designation?->name ?? '—' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    @foreach($grid['days'] as $day)
                                        @php($cell = $row['cells'][$day['day']])
                                        @php($meta = $legend[$cell['state']] ?? null)
                                        <td class="att-cell {{ $day['weekend'] ? 'att-weekend' : '' }}" title="{{ $cell['title'] }}">
                                            @if($meta)
                                                <span class="att-icon {{ $meta['cls'] }}">@if(str_starts_with($meta['icon'], 'fa'))<i class="{{ $meta['icon'] }}"></i>@else{{ $meta['icon'] }}@endif</span>
                                                @if(!empty($cell['subs']))
                                                    <span class="att-subs">
                                                        @foreach($cell['subs'] as $sub)
                                                            @if(isset($subLegend[$sub]))<i class="{{ $subLegend[$sub]['icon'] }} {{ $subLegend[$sub]['cls'] }}"></i>@endif
                                                        @endforeach
                                                    </span>
                                                @endif
                                            @endif
                                        </td>
                                    @endforeach
                                    <td class="att-total-col">
                                        <strong>{{ rtrim(rtrim(number_format($row['total']['present'], 1), '0'), '.') }}</strong><span class="text-muted">/{{ $row['total']['working'] }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($grid['days']) + 2 }}" class="text-center py-4 text-muted">{{ __('No employees found.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $employeesPage->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .att-legend { display: flex; flex-wrap: wrap; gap: 18px; align-items: center; font-size: 12.5px; }
    .att-legend-item { display: inline-flex; align-items: center; gap: 6px; color: #445; }
    .att-grid-scroll { overflow-x: auto; border: 1px solid #e6e9ee; border-radius: 8px; }
    table.att-grid { border-collapse: separate; border-spacing: 0; width: max-content; min-width: 100%; font-size: 12px; }
    table.att-grid th, table.att-grid td { border-bottom: 1px solid #eef1f4; border-right: 1px solid #eef1f4; text-align: center; }
    .att-month-title { padding: 10px; font-weight: 600; color: #2b333c; background: #fafbfc; }
    .att-day-head { width: 34px; min-width: 34px; padding: 5px 2px; background: #fafbfc; line-height: 1.1; }
    .att-day-num { display: block; font-weight: 600; color: #2b333c; }
    .att-day-wd { display: block; font-size: 9.5px; color: #97a1ac; text-transform: uppercase; }
    .att-cell { width: 34px; min-width: 34px; height: 42px; padding: 2px; }
    .att-weekend { background: #f6f8fa; }
    .att-emp-col { position: sticky; left: 0; z-index: 3; background: #fff; text-align: left !important; min-width: 210px; padding: 8px 12px; box-shadow: 2px 0 4px -2px rgba(0,0,0,.08); }
    thead .att-emp-col, thead .att-total-col { z-index: 4; background: #fafbfc; }
    .att-total-col { position: sticky; right: 0; background: #fff; min-width: 64px; padding: 8px; white-space: nowrap; box-shadow: -2px 0 4px -2px rgba(0,0,0,.08); }
    .att-emp { display: flex; align-items: center; gap: 10px; }
    .att-emp-avatar { width: 34px; height: 34px; border-radius: 50%; object-fit: cover; background: #eef1f4; }
    .att-emp-name { font-weight: 600; color: #2b333c; }
    .att-emp-role { font-size: 11px; color: #97a1ac; }
    .att-icon { display: inline-flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; }
    .att-present { color: #16a34a; }
    .att-absent  { color: #ef4444; }
    .att-half    { color: #f59e0b; }
    .att-leave   { color: #ef4444; }
    .att-holiday { color: #eab308; }
    .att-dayoff  { color: #b8c0c9; }
    .att-future  { color: #dfe4ea; font-size: 6px; }
    .att-none    { color: #d5dbe1; }
    .att-subs { display: block; line-height: 1; margin-top: 1px; }
    .att-subs i { font-size: 8.5px; margin: 0 1px; }
    .att-sub { display: inline-flex; }
    .sub-late  { color: #f59e0b; }
    .sub-early { color: #ef4444; }
    .sub-ot    { color: #3b82f6; }
</style>
@endpush

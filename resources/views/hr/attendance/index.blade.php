@extends('layouts.backend')
@section('title', 'Attendance')

@section('content')
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center">
        <h1><i class="icon-clock"></i> {{ __('Attendance') }}</h1>
        <div class="d-flex gap-2">
            @if($canManageAttendance && $hasAllAccess)
                <a href="{{ route('attendance.template-download') }}" class="btn btn-custom-default">
                    <i class="icon-doc"></i> {{ __('Template Download') }}
                </a>
            @endif
            <a href="{{ route('attendance.export', ['from_date' => $filters['from_date'], 'to_date' => $filters['to_date'], 'employee_id' => $filters['employee_id']]) }}"
                class="btn btn-custom-default">
                <i class="icon-cloud-download"></i> {{ __('Export Excel (CSV)') }}
            </a>
        </div>
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            <div class=" mb-3" id="attendance-entry-form">
                <div class="content_wrapper content-padded">
                    <h5 class="table_banner_title mb-3">{{ __('Manual Attendance Entry') }}</h5>
                    <form method="POST" action="{{ route('attendance.store') }}" class="row g-2">
                        @csrf
                        @php($currentAttendanceTime = optional(\App\Support\DateSystem::inCompanyZone(now()))->format('h:i A') ?? now()->format('h:i A'))
                        @php($currentAttendanceDate = optional(\App\Support\DateSystem::inCompanyZone(now()))->format('Y-m-d') ?? now()->format('Y-m-d'))
                        @if($canManageAttendance && $hasAllAccess)
                            <div class="col-md-3">
                                <label>{{ __('Employee') }}</label>
                                <select name="employee_id" class="form-control js-example-basic-single">
                                    @foreach($employees as $employee)
                                        @php($name = trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')))
                                        <option value="{{ $employee->id }}" {{ (int) old('employee_id', $currentEmployeeId) === (int) $employee->id ? 'selected' : '' }}>
                                            {{ $name !== '' ? $name : 'Employee' }} ({{ $employee->employee_code }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <div class="col-md-3">
                            {{-- Without the date-time permission the entry is pinned to today,
                                 so the field is shown but not pickable. --}}
                            <x-date-field name="attendance_date" :label="__('Date')"
                                          :value="$currentAttendanceDate"
                                          :readonly="! $canEditAttendanceDateTime"
                                          wrapper-class="" required />
                        </div>
                        @if($canManageAttendance)
                            <div class="col-md-2">
                                <label>{{ __('Entry Type') }}</label>
                                @php($entryType = old('entry_type', 'checkin'))
                                <select name="entry_type" class="form-control" required>
                                    <option value="checkin" {{ $entryType === 'checkin' ? 'selected' : '' }}>{{ __('Check-in') }}</option>
                                    <option value="checkout" {{ $entryType === 'checkout' ? 'selected' : '' }}>{{ __('Check-out') }}</option>
                                </select>
                            </div>
                        @else
                            <div class="col-md-2">
                                <label>{{ __('Next Action') }}</label>
                                <div class="form-control-plaintext">
                                    <span class="badge {{ ($nextEntryType ?? 'checkin') === 'checkout' ? 'bg-warning' : 'bg-success' }}">
                                        {{ ($nextEntryType ?? 'checkin') === 'checkout' ? __('Check-out') : __('Check-in') }}
                                    </span>
                                </div>
                            </div>
                        @endif
                        <div class="col-md-2">
                            <label>{{ __('Time (hh:mm AM/PM)') }}</label>
                            <input type="text" name="entry_time"
                                class="form-control {{ $canEditAttendanceDateTime ? 'attendance-time-picker' : '' }}"
                                placeholder="09:01 AM"
                                autocomplete="off"
                                value="{{ old('entry_time', $currentAttendanceTime) }}"
                                {{ $canEditAttendanceDateTime ? '' : 'readonly' }}
                                required
                            >
                        </div>
                        <div class="col-md-2">
                                <label>{{ __('Remarks') }}</label>
                                <input type="text" name="remarks" class="form-control" value="{{ old('remarks') }}" placeholder="{{ __('Optional') }}">
                            </div>
                        <div class="col-md-12 mt-2">
                            @if($canManageAttendance)
                                <button type="submit" class="btn btn-custom"><i class="icon-plus"></i> {{ __('Add Attendance') }}</button>
                            @else
                                <button type="submit" class="btn btn-custom">
                                    <i class="icon-clock"></i>
                                    {{ ($nextEntryType ?? 'checkin') === 'checkout' ? __('Check Out Now') : __('Check In Now') }}
                                </button>
                                <small class="text-muted d-block mt-1">{{ __('The system records your next check-in or check-out automatically.') }}</small>
                            @endif
                            </div>
                    </form>
                </div>
            </div>

            @if($canManageAttendance && $hasAllAccess)
                <div class="mb-3">
                    <div class="content_wrapper content-padded">
                        <h5 class="table_banner_title mb-3">{{ __('Excel Import (CSV)') }}</h5>
                        <form method="POST" action="{{ route('attendance.import') }}" enctype="multipart/form-data" class="row g-2">
                            @csrf
                            <div class="col-md-6">
                                <label>{{ __('Upload CSV File') }}</label>
                                <div class="d-flex align-items-center gap-2">
                                    <input id="attendance_file" type="file" name="attendance_file" accept=".csv,text/csv" required>
                                    <label for="attendance_file" class="btn btn-custom mb-0">{{ __('Choose CSV') }}</label>
                                    <span id="attendance_file_name" class="text-muted">{{ __('No file selected') }}</span>
                                    </div>
                                        <small class="text-muted">Use template columns: employee_code, attendance_date, entry_type, entry_time, remarks.</small>
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <button type="submit" class="btn btn-custom"><i class="icon-cloud-upload"></i> {{ __('Import Attendance') }}</button>
                                </div>
                        </form>
                    </div>
                </div>
            @endif

            <div>
                <div class="content_wrapper content-padded">
                    <h5 class="table_banner_title mb-3">{{ __('Attendance History') }}</h5>

                    <form method="GET" class="row g-2 mb-3">
                        <div class="col-md-2">
                            <x-date-field name="from_date" :value="$filters['from_date']" :placeholder="__('From date')" wrapper-class="" />
                        </div>
                        <div class="col-md-2">
                            <x-date-field name="to_date" :value="$filters['to_date']" :placeholder="__('To date')" wrapper-class="" />
                        </div>
                        <div class="col-md-3">
                            <select name="employee_id" class="form-control js-example-basic-single">
                                <option value="0">{{ __('All Employees') }}</option>
                                @foreach($employees as $employee)
                                    @php($name = trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')))
                                    <option value="{{ $employee->id }}" {{ (int) $filters['employee_id'] === (int) $employee->id ? 'selected' : '' }}>
                                        {{ $name !== '' ? $name : 'Employee' }} ({{ $employee->employee_code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="per_page" class="form-control">
                                @foreach([10,20,50,100] as $size)
                                    <option value="{{ $size }}" {{ (int) $filters['per_page'] === $size ? 'selected' : '' }}>{{ $size }} / page</option>
                                @endforeach
                                </select>
                        </div>
                        <div class="col-md-3 d-flex gap-2">
                            <button type="submit" class="btn btn-custom"><i class="icon-magnifier"></i> {{ __('Filter') }}</button>
                                <a href="{{ route('attendance.index') }}" class="btn btn-custom-default"><i class="icon-refresh"></i></a>
                            </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>{{ __('Date') }}</th>
                                    <th>{{ __('Employee') }}</th>
                                    <th>{{ __('Code') }}</th>
                                    <th>{{ __('First Check-in') }}</th>
                                    <th>{{ __('Last Check-out') }}</th>
                                    <th>{{ __('Duration') }}</th>
                                    <th>{{ __('Entries (Same Day)') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($attendanceRows as $row)
                                    <tr>
                                        <td>{{ \Illuminate\Support\Carbon::parse($row->attendance_date)->format('Y-m-d') }}</td>
                                        <td>{{ trim(($row->first_name ?? '').' '.($row->last_name ?? '')) ?: '-' }}</td>
                                        <td>{{ $row->employee_code ?: '-' }}</td>
                                        <td>{{ $row->first_check_in_at ? \Illuminate\Support\Carbon::parse($row->first_check_in_at)->format('H:i') : '-' }}</td>
                                        <td>{{ $row->last_check_out_at ? \Illuminate\Support\Carbon::parse($row->last_check_out_at)->format('H:i') : '-' }}</td>
                                        <td>
                                            @php($workedMinutes = max(0, (int) ($row->worked_minutes ?? 0)))
                                            {{ intdiv($workedMinutes, 60) }}h {{ $workedMinutes % 60 }}m
                                        </td>
                                        <td>{{ (int) $row->total_entries }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center">{{ __('No attendance data found for selected filters.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $attendanceRows->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/timepicker/1.3.5/jquery.timepicker.min.css">
@endpush

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/timepicker/1.3.5/jquery.timepicker.min.js"></script>
<script>

    (function () {

        if ($.fn.timepicker) {
            $('.attendance-time-picker').timepicker({
                timeFormat: 'h:mm p',
                step: 5,
                scrollDefault: 'now',
                forceRoundTime: true,
                dropdown: true
            });
        }

        var attendanceFile = document.getElementById('attendance_file');
        var attendanceFileName = document.getElementById('attendance_file_name');
        if (attendanceFile && attendanceFileName) {
            attendanceFile.addEventListener('change', function () {
                if (attendanceFile.files && attendanceFile.files.length > 0) {
                    attendanceFileName.textContent = attendanceFile.files[0].name;
                } else {
                    attendanceFileName.textContent = @json(__('No file selected'));
                }
            });
        }
    })();
</script>
@endpush

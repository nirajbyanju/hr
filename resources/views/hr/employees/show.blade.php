@extends('layouts.backend')

@section('content')
<div class="wrapper-page">
    @php
        $canUpdateEmployee = auth()->user()?->hasPermission('employee.update') ?? false;
    @endphp
    <div class="page-title d-flex justify-content-between align-items-center">
        <h1><i class="icon-user"></i> {{ __('Employee Profile') }}</h1>
        @if($canUpdateEmployee)
            <a href="{{ route('employees.edit', $employee) }}" class="btn btn-custom"><i class="icon-pencil"></i> {{ __('Edit') }}</a>
        @endif
    </div>

    <div class="page-content">
        <div class="container-fluid">
            <div>
                <div class="content_wrapper content-padded">
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <div class="employee-profile-avatar">
                            <img src="{{ $employee->avatar_path ? asset($employee->avatar_path) : asset(\App\Support\DefaultAvatar::forGender($employee->gender)) }}" alt="Employee Avatar">

                        </div>
                        <h4 class="mb-0">{{ trim($employee->first_name.' '.$employee->last_name) }} <small class="text-muted">({{ $employee->employee_code }})</small></h4>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-4"><strong>{{ __('Status:') }}</strong> {{ __(ucfirst(str_replace('_',' ', $employee->employment_status))) }}</div>
                        <div class="col-md-4"><strong>{{ __('Type:') }}</strong> {{ __(ucfirst(str_replace('_',' ', $employee->employment_type))) }}</div>
                        <div class="col-md-4"><strong>{{ __('Join Date:') }}</strong> {{ $employee->date_of_joining }}</div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-4"><strong>{{ __('Department:') }}</strong> {{ $employee->department?->name ?? '-' }}</div>
                        <div class="col-md-4"><strong>{{ __('Designation:') }}</strong> {{ $employee->designation?->name ?? '-' }}</div>
                        <div class="col-md-4"><strong>{{ __('Manager:') }}</strong> {{ $employee->manager ? trim($employee->manager->first_name.' '.$employee->manager->last_name) : '-' }}</div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-4">
                            <strong>{{ __('Work Shift:') }}</strong>
                            @if($employee->shift)
                                {{ $employee->shift->name }} ({{ $employee->shift->hoursLabel() }})
                            @else
                                <span class="text-muted">{{ __('Company default hours') }}</span>
                            @endif
                        </div>
                        <div class="col-md-4">
                            <strong>{{ __('Attendance Policy:') }}</strong>
                            @if($employee->attendancePolicy)
                                {{ $employee->attendancePolicy->name }}
                            @else
                                <span class="text-muted">{{ __('Company default policy') }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-4"><strong>{{ __('Phone:') }}</strong> {{ $employee->phone ?: '-' }}</div>
                        <div class="col-md-4"><strong>{{ __('Work Email:') }}</strong> {{ $employee->work_email ?: '-' }}</div>
                        <div class="col-md-4"><strong>{{ __('Linked User:') }}</strong> {{ $employee->user?->email ?? '-' }}</div>
                    </div>

                    @if($employee->subordinates->count() > 0)
                        <hr>
                        <h5>{{ __('Subordinates') }}</h5>
                        <ul>
                            @foreach($employee->subordinates as $subordinate)
                                <li>{{ trim($subordinate->first_name.' '.$subordinate->last_name) }} ({{ $subordinate->employee_code }})</li>
                            @endforeach
                        </ul>
                    @endif

                    <div class="mt-3">
                        <a href="{{ route('employees.index') }}" class="btn btn-custom-default"><i class="icon-arrow-left"></i> {{ __('Back') }}</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

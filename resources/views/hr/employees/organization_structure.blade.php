@extends('layouts.backend')

@section('content')
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center">
        <h1><i class="icon-share-alt"></i> {{ __('Organization Structure') }}</h1>
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            @if($authEmployee)
                <div class="card no-border mb-3">
                    <div class="content_wrapper content-padded">
                        <h5 class="mb-3">{{ __('My Reporting Line') }}</h5>
                        @if($supervisorChain->isEmpty())
                            <div class="alert alert-info mb-2">{{ __('No supervisor assigned for your profile.') }}</div>
                        @else
                            <ol class="mb-0">
                                @foreach($supervisorChain as $supervisor)
                                    <li>
                                        {{ trim($supervisor->first_name.' '.$supervisor->last_name) }}
                                        ({{ $supervisor->employee_code }})
                                        @if($supervisor->designation?->name)
                                            - {{ $supervisor->designation->name }}
                                        @endif
                                    </li>
                                @endforeach
                            </ol>
                        @endif

                        <hr>
                        <h5 class="mb-3">{{ __('My Direct Subordinates') }}</h5>
                        @if($mySubordinates->isEmpty())
                            <div class="alert alert-info mb-0">{{ __('No direct subordinates found.') }}</div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-bordered mb-0">
                                    <thead>
                                        <tr>
                                            <th>{{ __('Code') }}</th>
                                            <th>{{ __('Name') }}</th>
                                            <th>{{ __('Department') }}</th>
                                            <th>{{ __('Designation') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($mySubordinates as $item)
                                            <tr>
                                                <td>{{ $item->employee_code }}</td>
                                                <td>{{ trim($item->first_name.' '.$item->last_name) }}</td>
                                                <td>{{ $item->department?->name ?? '-' }}</td>
                                                <td>{{ $item->designation?->name ?? '-' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <div class="card no-border">
                <div class="content_wrapper content-padded">
                    <h5 class="mb-3">{{ __('Company Employee Structure') }}</h5>
                    @php($grouped = $employees->groupBy(fn ($item) => $item->department?->name ?? __('Unassigned Department')))

                    @forelse($grouped as $departmentName => $items)
                        <h6 class="mt-3">{{ $departmentName }}</h6>
                        <div class="table-responsive mb-3">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>{{ __('Code') }}</th>
                                        <th>{{ __('Employee') }}</th>
                                        <th>{{ __('Designation') }}</th>
                                        <th>{{ __('Reports To') }}</th>
                                        <th>{{ __('Status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($items as $employee)
                                        <tr>
                                            <td>{{ $employee->employee_code }}</td>
                                            <td>{{ trim($employee->first_name.' '.$employee->last_name) }}</td>
                                            <td>{{ $employee->designation?->name ?? '-' }}</td>
                                            <td>
                                                @if($employee->manager)
                                                    {{ trim($employee->manager->first_name.' '.$employee->manager->last_name) }} ({{ $employee->manager->employee_code }})
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>{{ __(ucfirst(str_replace('_', ' ', $employee->employment_status))) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @empty
                        <div class="alert alert-info mb-0">{{ __('No employee data found.') }}</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@extends('layouts.backend')
@section('title', 'Leave Policies')

@section('content')
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center">
        <h1><i class="icon-calendar"></i> {{ __('Leave Policies (Salary Grade Wise)') }}</h1>
        @if(auth()->user()?->hasPermission('leave.manage-quotas'))
            <a href="{{ route('leave-policies.create') }}" class="btn btn-custom"><i class="icon-plus"></i> {{ __('Add Policy') }}</a>
        @endif
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            <div>
                <div class="content_wrapper content-padded">
                    <form method="GET" class="row g-2 mb-3">
                        <div class="col-md-2">
                            <select name="year" class="form-control">
                                @for($y = $currentYear + 1; $y >= $currentYear - 5; $y--)
                                    <option value="{{ $y }}" {{ (int) $filters['year'] === (int) $y ? 'selected' : '' }}>{{ $y }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="salary_grade_id" class="form-control">
                                <option value="0">{{ __('All Salary Grades') }}</option>
                                @foreach($salaryGrades as $salaryGrade)
                                    <option value="{{ $salaryGrade->id }}" {{ (int) $filters['salary_grade_id'] === (int) $salaryGrade->id ? 'selected' : '' }}>
                                        {{ $salaryGrade->grade_name }} ({{ $salaryGrade->grade_code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="leave_category_id" class="form-control">
                                <option value="0">{{ __('All Leave Categories') }}</option>
                                @foreach($leaveCategories as $leaveCategory)
                                    <option value="{{ $leaveCategory->id }}" {{ (int) $filters['leave_category_id'] === (int) $leaveCategory->id ? 'selected' : '' }}>
                                        {{ $leaveCategory->name }} ({{ $leaveCategory->code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="status" class="form-control">
                                <option value="">{{ __('All Status') }}</option>
                                <option value="active" {{ $filters['status'] === 'active' ? 'selected' : '' }}>{{ __('Active') }}</option>
                                <option value="inactive" {{ $filters['status'] === 'inactive' ? 'selected' : '' }}>{{ __('Inactive') }}</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex gap-2">
                            <button class="btn btn-custom" type="submit"><i class="icon-magnifier"></i></button>
                            <a href="{{ route('leave-policies.index') }}" class="btn btn-custom-default"><i class="icon-refresh"></i></a>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>{{ __('Category') }}</th>
                                    <th>{{ __('Salary Grade') }}</th>
                                    <th>{{ __('Effective') }}</th>
                                    <th>{{ __('Allocated') }}</th>
                                    <th>{{ __('Carry Forward') }}</th>
                                    <th>{{ __('Earned Leave') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($policies as $policy)
                                    <tr>
                                        <td>{{ $policy->leaveCategory?->name }} ({{ $policy->leaveCategory?->code }})</td>
                                        <td>{{ $policy->salaryGrade?->grade_name }} ({{ $policy->salaryGrade?->grade_code }})</td>
                                        <td>
                                            {{ $policy->effective_from_year }}
                                            @if($policy->effective_to_year)
                                                - {{ $policy->effective_to_year }}
                                            @endif
                                        </td>
                                        <td>{{ number_format((float) $policy->days_allocated, 2) }} {{ $policy->is_prorated ? __('(Prorated)') : '' }}</td>
                                        <td>
                                            {{ __(ucfirst($policy->carry_forward_mode)) }}
                                            @if($policy->carry_forward_mode === 'limited')
                                                ({{ number_format((float) ($policy->carry_forward_limit ?? 0), 2) }})
                                            @endif
                                        </td>
                                        <td>
                                            @if($policy->is_earned_leave)
                                                {{ __(ucfirst((string) $policy->earned_credit_frequency)) }}: {{ number_format((float) ($policy->earned_credit_days ?? 0), 2) }}
                                            @else
                                                No
                                            @endif
                                        </td>
                                        <td>
                                            @if($policy->is_active)
                                                <span class="badge bg-success">{{ __('Active') }}</span>
                                            @else
                                                <span class="badge bg-secondary">{{ __('Inactive') }}</span>
                                            @endif
                                        </td>
                                        <td class="action-buttons">
                                            @if(auth()->user()?->hasPermission('leave.manage-quotas'))
                                                <a href="{{ route('leave-policies.edit', $policy) }}" title="{{ __('Edit') }}">
                                                    <i class="icon-pencil"></i>
                                                </a>
                                                <form method="POST" action="{{ route('leave-policies.destroy', $policy) }}" onsubmit="return confirm('Delete this leave policy?');" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" title="{{ __('Delete') }}"><i class="icon-trash"></i></button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center">{{ __('No leave policies found.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $policies->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

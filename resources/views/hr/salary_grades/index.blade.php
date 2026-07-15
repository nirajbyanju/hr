@extends('layouts.backend')

@section('content')
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center">
        <h1><i class="icon-wallet"></i> {{ __('Salary Grades') }}</h1>
        @if(auth()->user()?->hasAnyPermission(['salary_grade.create', 'payroll.manage-salary-templates']))
            <a href="{{ route('salary-grades.create') }}" class="btn btn-custom"><i class="icon-plus"></i> {{ __('Add Salary Grade') }}</a>
        @endif
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            <div class="card no-border">
                <div class="content_wrapper content-padded">
                    <form method="GET" class="row g-2 mb-3">
                        <div class="col-md-4">
                            <input type="text" name="q" value="{{ $filters['q'] }}" class="form-control" placeholder="{{ __('Search grade/band/code/description') }}">
                        </div>
                        <div class="col-md-2">
                            <select name="status" class="form-control">
                                <option value="">{{ __('All Status') }}</option>
                                <option value="active" {{ $filters['status'] === 'active' ? 'selected' : '' }}>{{ __('Active') }}</option>
                                <option value="inactive" {{ $filters['status'] === 'inactive' ? 'selected' : '' }}>{{ __('Inactive') }}</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="per_page" class="form-control">
                                @foreach([10,20,50,100] as $size)
                                    <option value="{{ $size }}" {{ (int) $filters['per_page'] === $size ? 'selected' : '' }}>{{ $size }} / page</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 d-flex gap-2">
                            <button class="btn btn-custom" type="submit"><i class="icon-magnifier"></i> {{ __('Filter') }}</button>
                            <a href="{{ route('salary-grades.index') }}" class="btn btn-custom-default"><i class="icon-refresh"></i> {{ __('Reset') }}</a>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>{{ __('Grade Name') }}</th>
                                    <th>{{ __('Grade Code') }}</th>
                                    <th>{{ __('Band') }}</th>
                                    <th>{{ __('Salary Range') }}</th>
                                    <th>{{ __('Employees') }}</th>
                                    <th>{{ __('Leave Policies') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($salaryGrades as $salaryGrade)
                                    <tr>
                                        <td>{{ $salaryGrade->grade_name }}</td>
                                        <td>{{ $salaryGrade->grade_code }}</td>
                                        <td>{{ $salaryGrade->band_name ?: '-' }}</td>
                                        <td>
                                            @if($salaryGrade->min_salary !== null || $salaryGrade->max_salary !== null)
                                                {{ $salaryGrade->min_salary !== null ? number_format((float) $salaryGrade->min_salary, 2) : '-' }}
                                                -
                                                {{ $salaryGrade->max_salary !== null ? number_format((float) $salaryGrade->max_salary, 2) : '-' }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>{{ $salaryGrade->employees_count }}</td>
                                        <td>{{ $salaryGrade->leave_policies_count }}</td>
                                        <td>
                                            @if($salaryGrade->is_active)
                                                <span class="badge bg-success">{{ __('Active') }}</span>
                                            @else
                                                <span class="badge bg-secondary">{{ __('Inactive') }}</span>
                                            @endif
                                        </td>
                                        <td class="action-buttons">
                                            @if(auth()->user()?->hasAnyPermission(['salary_grade.update', 'payroll.manage-salary-templates']))
                                                <a href="{{ route('salary-grades.edit', $salaryGrade) }}" title="{{ __('Edit Salary Grade') }}">
                                                    <i class="icon-pencil"></i>
                                                </a>
                                            @endif
                                            @if(auth()->user()?->hasPermission('salary_grade.delete'))
                                                <form method="POST" action="{{ route('salary-grades.destroy', $salaryGrade) }}" onsubmit="return confirm('Delete this salary grade?');" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" title="{{ __('Delete Salary Grade') }}"><i class="icon-trash"></i></button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center">{{ __('No salary grades found.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $salaryGrades->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

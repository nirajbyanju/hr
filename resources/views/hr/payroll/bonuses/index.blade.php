@extends('layouts.backend')

@section('content')
@php
    $authUser = auth()->user();
    $canGenerateBonus = $authUser?->hasAnyPermission(['payroll.manage-bonus', 'bonus.generate-batch']) ?? false;
    $canDeleteBonus = $authUser?->hasAnyPermission(['payroll.manage-bonus', 'bonus.delete']) ?? false;
@endphp
<div class="wrapper-page">
    <div class="page-title"><h1><i class="icon-present"></i> {{ __('Bonuses') }}</h1></div>
    @include('partials.flash')
    <div class="page-content">
        <div class="container-fluid">
            <div>
                <div class="content_wrapper content-padded">
                    @if($canGenerateBonus)
                        <form method="POST" action="{{ route('payroll.bonuses.generate') }}" class="row g-2 mb-4">
                            @csrf
                            <div class="col-md-3"><select name="employee_id" class="form-control js-example-basic-single"><option value="0">{{ __('All Active Employees') }}</option>@foreach($employees as $employee)<option value="{{ $employee->id }}">{{ trim($employee->first_name.' '.$employee->last_name) }} ({{ $employee->employee_code }})</option>@endforeach</select></div>
                            <div class="col-md-2"><input type="text" name="title" class="form-control" value="{{ old('title', 'Festival Bonus') }}" placeholder="{{ __('Title') }}" required></div>
                            <div class="col-md-2">
                                <select name="calculation_type" class="form-control" required>
                                    <option value="basic_percent">{{ __('Basic Salary %') }}</option>
                                    <option value="gross_percent">{{ __('Basic + Allowance %') }}</option>
                                    <option value="fixed">{{ __('Custom Fixed Amount') }}</option>
                                </select>
                            </div>
                            <div class="col-md-1"><input type="number" step="0.01" min="0" max="100" name="percentage" class="form-control" placeholder="%"></div>
                            <div class="col-md-1"><input type="number" step="0.01" min="0" name="amount" class="form-control" placeholder="{{ __('Amount') }}"></div>
                            <div class="col-md-2"><input type="text" name="bonus_date" class="form-control datetimepicker" value="{{ now()->toDateString() }}" placeholder="{{ __('Bonus date') }}" required></div>
                            <div class="col-md-1"><input type="text" name="bonus_type" class="form-control" value="policy" placeholder="{{ __('Type') }}" required></div>
                            <div class="col-md-12"><button class="btn btn-custom" type="submit"><i class="icon-plus"></i> {{ __('Generate Bonus') }}</button></div>
                            <div class="col-md-12"><textarea name="remarks" class="form-control" rows="2" placeholder="{{ __('Remarks') }}"></textarea></div>
                        </form>
                    @endif

                    <form method="GET" class="row g-2 mb-3">
                        <div class="col-md-4"><select name="employee_id" class="form-control"><option value="0">{{ __('All Employees') }}</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" {{ (int)$filters['employee_id']===$employee->id?'selected':'' }}>{{ trim($employee->first_name.' '.$employee->last_name) }} ({{ $employee->employee_code }})</option>@endforeach</select></div>
                        <div class="col-md-2"><select name="per_page" class="form-control">@foreach([10,20,50,100] as $size)<option value="{{ $size }}" {{ (int)$filters['per_page']===$size?'selected':'' }}>{{ $size }} / page</option>@endforeach</select></div>
                        <div class="col-md-6 d-flex gap-2"><button class="btn btn-custom" type="submit"><i class="icon-magnifier"></i> {{ __('Filter') }}</button><a href="{{ route('payroll.bonuses.index') }}" class="btn btn-custom-default"><i class="icon-refresh"></i> {{ __('Reset') }}</a></div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead><tr><th>{{ __('Employee') }}</th><th>{{ __('Title') }}</th><th>{{ __('Type') }}</th><th>{{ __('Date') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Created By') }}</th><th>{{ __('Actions') }}</th></tr></thead>
                            <tbody>
                                @forelse($bonuses as $bonus)
                                    <tr>
                                        <td>{{ trim($bonus->employee?->first_name.' '.$bonus->employee?->last_name) }} <small class="text-muted">({{ $bonus->employee?->employee_code }})</small></td>
                                        <td>{{ $bonus->title }}</td>
                                        <td>{{ __(ucfirst($bonus->bonus_type)) }}</td>
                                        <td>{{ $bonus->bonus_date }}</td>
                                        <td>{{ number_format((float)$bonus->amount, 2) }}</td>
                                        <td>{{ $bonus->creator?->name ?: '-' }}</td>
                                        <td class="action-buttons">
                                            @if($canDeleteBonus)
                                                <form method="POST" action="{{ route('payroll.bonuses.destroy', $bonus) }}" onsubmit="return confirm('Delete this bonus?');">@csrf @method('DELETE')<button type="submit"><i class="icon-trash"></i></button></form>
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="text-center">{{ __('No bonuses found.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    {{ $bonuses->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

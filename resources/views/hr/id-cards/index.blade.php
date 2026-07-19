@extends('layouts.backend')

@section('content')
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center">
        <h1><i class="fa fa-id-card"></i> {{ __('Employee ID Cards') }}</h1>
        <a href="{{ route('attendance.scan.index') }}" class="btn btn-outline-secondary btn-sm"><i class="icon-screen-smartphone"></i> {{ __('Attendance Scanner') }}</a>
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            <div class="content_wrapper content-padded">
                <form method="GET" class="row g-2 mb-3">
                    <div class="col-md-4">
                        <input type="text" name="q" value="{{ $filters['q'] }}" class="form-control" placeholder="{{ __('Search code / name') }}">
                    </div>
                    <div class="col-md-3">
                        <select name="department_id" class="form-control">
                            <option value="0">{{ __('All Departments') }}</option>
                            @foreach($departments as $department)
                                <option value="{{ $department->id }}" {{ (int) $filters['department_id'] === $department->id ? 'selected' : '' }}>{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="card" class="form-control">
                            <option value="">{{ __('All employees') }}</option>
                            <option value="with" {{ $filters['card'] === 'with' ? 'selected' : '' }}>{{ __('Has active card') }}</option>
                            <option value="without" {{ $filters['card'] === 'without' ? 'selected' : '' }}>{{ __('No card yet') }}</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-custom w-100"><i class="icon-magnifier"></i> {{ __('Filter') }}</button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>{{ __('Employee') }}</th>
                                <th>{{ __('Department') }}</th>
                                <th>{{ __('Designation') }}</th>
                                <th>{{ __('ID Card') }}</th>
                                <th class="text-end">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($employees as $employee)
                                @php($cardRow = $employee->activeIdCard)
                                <tr>
                                    <td>
                                        <strong>{{ trim($employee->first_name . ' ' . $employee->last_name) }}</strong><br>
                                        <small class="text-muted">{{ $employee->employee_code }}</small>
                                    </td>
                                    <td>{{ $employee->department->name ?? '—' }}</td>
                                    <td>{{ $employee->designation->name ?? '—' }}</td>
                                    <td>
                                        @if($cardRow)
                                            <span class="badge bg-success">{{ __('Active') }}</span>
                                            <div><small class="text-muted">{{ $cardRow->card_number }} · {{ __('printed') }} {{ $cardRow->print_count }}×</small></div>
                                        @else
                                            <span class="badge bg-secondary">{{ __('No card') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @if($cardRow)
                                            <a href="{{ route('id-cards.preview', $cardRow) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('View') }}"><i class="icon-eye"></i></a>
                                            @if($canPrint)
                                                <a href="{{ route('id-cards.print', $cardRow) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="{{ __('Print') }}"><i class="icon-printer"></i></a>
                                                <a href="{{ route('id-cards.pdf', $cardRow) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('PDF') }}"><i class="icon-doc"></i></a>
                                            @endif
                                        @elseif($canGenerate)
                                            <form method="POST" action="{{ route('id-cards.generate', $employee) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-custom"><i class="icon-plus"></i> {{ __('Generate') }}</button>
                                            </form>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-4">{{ __('No employees found.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{ $employees->links() }}
            </div>
        </div>
    </div>
</div>
@endsection

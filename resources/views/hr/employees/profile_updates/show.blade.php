@extends('layouts.backend')

@section('content')
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center">
        <h1><i class="icon-doc"></i> {{ __('Request Review') }}</h1>
        <a href="{{ route('employees.profile-updates.index') }}" class="btn btn-custom-default"><i class="icon-arrow-left"></i> {{ __('Back') }}</a>
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            <div class="card no-border">
                <div class="content_wrapper content-padded">
                    <h5>
                        {{ trim($requestItem->employee?->first_name.' '.$requestItem->employee?->last_name) }}
                        <small class="text-muted">({{ $requestItem->employee?->employee_code }})</small>
                    </h5>
                    <div class="row mt-2">
                        <div class="col-md-4"><strong>{{ __('Status:') }}</strong> {{ __(ucfirst($requestItem->approval_status)) }}</div>
                        <div class="col-md-4"><strong>{{ __('Submitted At:') }}</strong> {{ $requestItem->submitted_at?->format('Y-m-d H:i') ?? '-' }}</div>
                        <div class="col-md-4"><strong>{{ __('Submitted By:') }}</strong> {{ $requestItem->submittedBy?->name ?? '-' }}</div>
                    </div>

                    @php($payload = $requestItem->payload ?? [])
                    @php($requestedGeneralInfo = $payload['general_info'] ?? [])
                    @php($currentGeneralInfo = [
                        'first_name' => $requestItem->employee?->first_name,
                        'last_name' => $requestItem->employee?->last_name,
                        'gender' => $requestItem->employee?->gender,
                        'date_of_birth' => $requestItem->employee?->date_of_birth,
                        'phone' => $requestItem->employee?->phone,
                        'alternate_phone' => $requestItem->employee?->alternate_phone,
                        'marital_status' => $requestItem->employee?->marital_status,
                        'nid_number' => $requestItem->employee?->nid_number,
                        'passport_number' => $requestItem->employee?->passport_number,
                        'tax_id' => $requestItem->employee?->tax_id,
                        'notes' => $requestItem->employee?->notes,
                        'avatar_path' => $requestItem->employee?->avatar_path,
                    ])
                    @php($requestedAvatarPath = is_array($requestedGeneralInfo) ? ($requestedGeneralInfo['avatar_path'] ?? null) : null)

                    <hr>
                        <h5>{{ __('General Information') }}</h5>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="profile-review-avatar">
                                <span>{{ __('Current Image') }}</span>
                                <img src="{{ $requestItem->employee?->avatar_path ? asset($requestItem->employee->avatar_path) : asset('assets/img/user/default.jpg') }}" alt="Current profile image">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="profile-review-avatar">
                                <span>{{ __('Requested Image') }}</span>
                                <img src="{{ $requestedAvatarPath ? asset($requestedAvatarPath) : ($requestItem->employee?->avatar_path ? asset($requestItem->employee->avatar_path) : asset('assets/img/user/default.jpg')) }}" alt="Requested profile image">
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive mb-3">
                        <table class="table table-bordered">
                            <thead><tr><th>{{ __('Current') }}</th><th>{{ __('Requested') }}</th></tr></thead>
                            <tbody>
                            <tr>
                                <td><pre class="mb-0">{{ json_encode($currentGeneralInfo, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></td>
                                <td><pre class="mb-0">{{ json_encode($requestedGeneralInfo, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></td>
                            </tr>
                            </tbody>
                         </table>
                    </div>

                    <h5>{{ __('Read-only Organization Info') }}</h5>
                    <div class="table-responsive mb-3">
                        <table class="table table-bordered">
                            <thead><tr><th>{{ __('Field') }}</th><th>{{ __('Value') }}</th></tr></thead>
                            <tbody>
                                <tr><td>{{ __('Employee Code') }}</td><td>{{ $requestItem->employee?->employee_code ?: '-' }}</td></tr>
                                <tr><td>{{ __('Work Email') }}</td><td>{{ $requestItem->employee?->work_email ?: '-' }}</td></tr>
                                <tr><td>{{ __('Login Email') }}</td><td>{{ $requestItem->employee?->user?->email ?: '-' }}</td></tr>
                                <tr><td>{{ __('Department') }}</td><td>{{ $requestItem->employee?->department?->name ?: '-' }}</td></tr>
                                <tr><td>{{ __('Designation') }}</td><td>{{ $requestItem->employee?->designation?->name ?: '-' }}</td></tr>
                                <tr><td>{{ __('Salary Grade') }}</td><td>{{ $requestItem->employee?->salaryGrade ? $requestItem->employee->salaryGrade->grade_name.' ('.$requestItem->employee->salaryGrade->grade_code.')' : '-' }}</td></tr>
                                <tr><td>{{ __('Reporting To') }}</td><td>{{ $requestItem->employee?->manager ? trim($requestItem->employee->manager->first_name.' '.$requestItem->employee->manager->last_name).' ('.$requestItem->employee->manager->employee_code.')' : '-' }}</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <h5>{{ __('Addresses') }}</h5>
                    <div class="table-responsive mb-3">
                        <table class="table table-bordered">
                            <thead><tr><th>{{ __('Current') }}</th><th>{{ __('Requested') }}</th></tr></thead>
                            <tbody>
                            <tr>
                                <td><pre class="mb-0">{{ json_encode($requestItem->employee?->addresses?->toArray() ?? [], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></td>
                                <td><pre class="mb-0">{{ json_encode($payload['addresses'] ?? [], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></td>
                            </tr>
                            </tbody>
                            </table>
                    </div>

                    <h5>{{ __('Bank Accounts') }}</h5>
                    <div class="table-responsive mb-3">
                        <table class="table table-bordered">
                            <thead><tr><th>{{ __('Current') }}</th><th>{{ __('Requested') }}</th></tr></thead>
                            <tbody><tr>
                                <td><pre class="mb-0">{{ json_encode($requestItem->employee?->bankAccounts?->toArray() ?? [], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></td>
                                <td><pre class="mb-0">{{ json_encode($payload['bank_accounts'] ?? [], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></td>
                            </tr></tbody>
                        </table>
                    </div>

                    <h5>{{ __('Emergency Contacts') }}</h5>
                    <div class="table-responsive mb-3">
                        <table class="table table-bordered">
                            <thead><tr><th>{{ __('Current') }}</th><th>{{ __('Requested') }}</th></tr></thead>
                            <tbody><tr>
                                <td><pre class="mb-0">{{ json_encode($requestItem->employee?->emergencyContacts?->toArray() ?? [], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></td>
                                <td><pre class="mb-0">{{ json_encode($payload['emergency_contacts'] ?? [], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></td>
                            </tr></tbody>
                        </table>
                    </div>

                    <h5>{{ __('Documents') }}</h5>
                    <div class="table-responsive mb-3">
                        <table class="table table-bordered">
                            <thead><tr><th>{{ __('Current') }}</th><th>{{ __('Requested') }}</th></tr></thead>
                            <tbody><tr>
                                <td><pre class="mb-0">{{ json_encode($requestItem->employee?->documents?->toArray() ?? [], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></td>
                                 <td><pre class="mb-0">{{ json_encode($payload['documents'] ?? [], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></td>
                            </tr></tbody>
                        </table>
                    </div>

                    @if($requestItem->approval_status === 'pending')
                        <hr>
                        <form method="POST" action="{{ route('employees.profile-updates.process', $requestItem) }}">
                            @csrf
                            <div class="form-group mb-3">
                                <label>{{ __('Review Comments') }}</label>
                                <textarea name="review_comments" class="form-control" rows="3" placeholder="{{ __('Add review comments') }}">{{ old('review_comments') }}</textarea>
                            </div>
                            <button class="btn btn-custom" type="submit" name="decision" value="approve">
                                <i class="icon-check"></i> {{ __('Approve & Apply') }}
                            </button>
                            <button class="btn btn-custom-default" type="submit" name="decision" value="reject">
                                <i class="icon-close"></i> {{ __('Reject') }}
                            </button>
                        </form>
                    @else
                        <div class="alert alert-secondary mt-3">
                            {{ __('Reviewed by :name on :date.', ['name' => $requestItem->reviewedBy?->name ?? __('N/A'), 'date' => $requestItem->reviewed_at?->format('Y-m-d H:i') ?? '-']) }}
                            <br>
                            Comments: {{ $requestItem->review_comments ?: '-' }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

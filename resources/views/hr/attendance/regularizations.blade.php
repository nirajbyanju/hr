@extends('layouts.backend')

@php
    $statusPill = [
        'pending'  => ['cls' => 'reg-pill-pending',  'label' => __('Pending')],
        'approved' => ['cls' => 'reg-pill-approved', 'label' => __('Approved')],
        'rejected' => ['cls' => 'reg-pill-rejected', 'label' => __('Rejected')],
    ];
@endphp

@section('content')
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h1><i class="icon-clock"></i> {{ __('Attendance Regularizations') }}</h1>
        <button type="button" class="btn btn-custom" data-reg-open-add><i class="icon-plus"></i> {{ __('Add Request') }}</button>
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            {{-- Toolbar --}}
            <div class="content_wrapper content-padded mb-3">
                <form method="GET" class="d-flex flex-wrap align-items-center gap-2">
                    <div class="reg-search flex-grow-1">
                        <i class="icon-magnifier"></i>
                        <input type="text" name="search" value="{{ $filters['search'] }}" class="form-control" placeholder="{{ __('Search employee or reason...') }}">
                    </div>
                    <button class="btn btn-custom" type="submit"><i class="icon-magnifier"></i> {{ __('Search') }}</button>
                    <div class="reg-status-filter">
                        <select name="status" class="form-control" onchange="this.form.submit()">
                            <option value="">{{ __('All Statuses') }}</option>
                            @foreach(['pending','approved','rejected'] as $st)
                                <option value="{{ $st }}" {{ $filters['status'] === $st ? 'selected' : '' }}>{{ __(ucfirst($st)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ms-auto d-flex align-items-center gap-2">
                        <span class="text-muted small">{{ __('Per Page') }}</span>
                        <select name="per_page" class="form-control reg-perpage" onchange="this.form.submit()">
                            @foreach([9, 12, 18, 30] as $size)
                                <option value="{{ $size }}" {{ (int) $filters['per_page'] === $size ? 'selected' : '' }}>{{ $size }}</option>
                            @endforeach
                        </select>
                    </div>
                </form>
            </div>

            {{-- Stat cards --}}
            <div class="row g-3 mb-3">
                @php($cards = [
                    ['label' => __('Total Requests'), 'value' => $stats['total'],    'sub' => __('All time'),   'icon' => 'icon-calendar',      'cls' => 'reg-stat-total'],
                    ['label' => __('Pending'),        'value' => $stats['pending'],  'sub' => __('Needs review'), 'icon' => 'icon-hourglass',   'cls' => 'reg-stat-pending'],
                    ['label' => __('Approved'),       'value' => $stats['approved'], 'sub' => __('Accepted'),   'icon' => 'icon-check',         'cls' => 'reg-stat-approved'],
                    ['label' => __('Rejected'),       'value' => $stats['rejected'], 'sub' => __('Declined'),   'icon' => 'icon-close',         'cls' => 'reg-stat-rejected'],
                ])
                @foreach($cards as $card)
                    <div class="col-md-3 col-sm-6">
                        <div class="content_wrapper content-padded reg-stat">
                            <div>
                                <div class="reg-stat-label">{{ $card['label'] }}</div>
                                <div class="reg-stat-value">{{ $card['value'] }}</div>
                                <div class="reg-stat-sub">{{ $card['sub'] }}</div>
                            </div>
                            <span class="reg-stat-icon {{ $card['cls'] }}"><i class="{{ $card['icon'] }}"></i></span>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Request cards --}}
            <div class="row g-3">
                @forelse($requests as $reg)
                    @php($emp = $reg->employee)
                    @php($photo = $emp?->avatar_path ? asset($emp->avatar_path) : asset(\App\Support\DefaultAvatar::forGender($emp?->gender)))
                    @php($pill = $statusPill[$reg->status] ?? $statusPill['pending'])
                    <div class="col-lg-6 col-xl-4">
                        <div class="content_wrapper content-padded reg-card h-100">
                            <div class="reg-card-head">
                                <div class="reg-emp">
                                    <img src="{{ $photo }}" alt="" class="reg-emp-avatar">
                                    <div>
                                        <div class="reg-emp-name">{{ trim(($emp?->first_name ?? '').' '.($emp?->last_name ?? '')) ?: '—' }}</div>
                                        <div class="reg-emp-meta">{{ $reg->attendance_date?->format('Y-m-d') }} <span class="reg-pill {{ $pill['cls'] }}">{{ $pill['label'] }}</span></div>
                                    </div>
                                </div>
                                <div class="reg-actions">
                                    @if($canReview && $reg->isPending())
                                        <form method="POST" action="{{ route('attendance.regularizations.approve', $reg) }}" onsubmit="return confirm('{{ __('Approve and update attendance?') }}');">@csrf<button type="submit" title="{{ __('Approve') }}" class="reg-act reg-act-approve"><i class="fa fa-check-circle-o"></i></button></form>
                                        <form method="POST" action="{{ route('attendance.regularizations.reject', $reg) }}" onsubmit="return confirm('{{ __('Reject this request?') }}');">@csrf<button type="submit" title="{{ __('Reject') }}" class="reg-act reg-act-reject"><i class="fa fa-times-circle-o"></i></button></form>
                                    @endif
                                    <button type="button" title="{{ __('View') }}" class="reg-act reg-act-view"
                                        data-reg-view
                                        data-employee="{{ trim(($emp?->first_name ?? '').' '.($emp?->last_name ?? '')) }}"
                                        data-date="{{ $reg->attendance_date?->format('Y-m-d') }}"
                                        data-status="{{ $pill['label'] }}"
                                        data-oin="{{ $reg->original_check_in_at?->format('H:i') ?? '—' }}"
                                        data-oout="{{ $reg->original_check_out_at?->format('H:i') ?? '—' }}"
                                        data-rin="{{ $reg->requested_check_in_at?->format('H:i') ?? '—' }}"
                                        data-rout="{{ $reg->requested_check_out_at?->format('H:i') ?? '—' }}"
                                        data-reason="{{ $reg->reason }}"
                                        data-remarks="{{ $reg->review_remarks }}"><i class="fa fa-eye"></i></button>
                                    @if($reg->isPending())
                                        <button type="button" title="{{ __('Edit') }}" class="reg-act reg-act-edit"
                                            data-reg-edit
                                            data-id="{{ $reg->id }}"
                                            data-employee="{{ trim(($emp?->first_name ?? '').' '.($emp?->last_name ?? '')) }}"
                                            data-date="{{ $reg->attendance_date?->format('Y-m-d') }}"
                                            data-rin="{{ $reg->requested_check_in_at?->format('H:i') }}"
                                            data-rout="{{ $reg->requested_check_out_at?->format('H:i') }}"
                                            data-reason="{{ $reg->reason }}"><i class="fa fa-pencil"></i></button>
                                    @endif
                                    <form method="POST" action="{{ route('attendance.regularizations.destroy', $reg) }}" onsubmit="return confirm('{{ __('Delete this request?') }}');">@csrf @method('DELETE')<button type="submit" title="{{ __('Delete') }}" class="reg-act reg-act-delete"><i class="fa fa-trash-o"></i></button></form>
                                </div>
                            </div>

                            <div class="reg-times">
                                <div class="reg-times-col">
                                    <div class="reg-times-label">{{ __('Original') }}</div>
                                    <div class="reg-time reg-time-old"><i class="fa fa-clock-o"></i> {{ $reg->original_check_in_at?->format('H:i') ?? '—' }}</div>
                                    <div class="reg-time reg-time-old"><i class="fa fa-clock-o"></i> {{ $reg->original_check_out_at?->format('H:i') ?? '—' }}</div>
                                </div>
                                <div class="reg-times-arrow"><i class="fa fa-long-arrow-right"></i></div>
                                <div class="reg-times-col text-end">
                                    <div class="reg-times-label">{{ __('Requested') }}</div>
                                    <div class="reg-time reg-time-new"><i class="fa fa-clock-o"></i> {{ $reg->requested_check_in_at?->format('H:i') ?? '—' }}</div>
                                    <div class="reg-time reg-time-new"><i class="fa fa-clock-o"></i> {{ $reg->requested_check_out_at?->format('H:i') ?? '—' }}</div>
                                </div>
                            </div>

                            <div class="reg-reason">
                                <div class="reg-reason-label"><i class="fa fa-comment-o"></i> {{ __('Reason') }}</div>
                                <div class="reg-reason-text">{{ $reg->reason }}</div>
                            </div>

                            <div class="reg-foot"><i class="fa fa-calendar-o"></i> {{ __('Requested on') }} : {{ $reg->created_at?->format('Y-m-d') }}</div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="content_wrapper content-padded text-center text-muted py-5">{{ __('No regularization requests found.') }}</div>
                    </div>
                @endforelse
            </div>

            <div class="mt-3">{{ $requests->links('pagination::bootstrap-5') }}</div>
        </div>
    </div>
</div>

{{-- Add / Edit modal --}}
<div class="reg-modal-backdrop" id="reg-modal" hidden>
    <div class="reg-modal">
        <div class="reg-modal-head">
            <h5 id="reg-modal-title">{{ __('Add New Regularization Request') }}</h5>
            <button type="button" class="reg-modal-x" data-reg-close>&times;</button>
        </div>
        <form method="POST" id="reg-form" action="{{ route('attendance.regularizations.store') }}">
            @csrf
            <input type="hidden" name="_method" id="reg-method" value="POST">
            <div class="reg-field">
                <label>{{ __('Employee') }}</label>
                <select name="employee_id" id="reg-employee" class="form-control" required>
                    <option value="">{{ __('Select employee') }}</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}">{{ trim($employee->first_name.' '.$employee->last_name) }} ({{ $employee->employee_code }})</option>
                    @endforeach
                </select>
            </div>
            <div class="reg-field">
                <label>{{ __('Attendance Record') }}</label>
                <select id="reg-record" class="form-control">
                    <option value="">{{ __('Select attendance record') }}</option>
                </select>
                <input type="hidden" name="attendance_date" id="reg-date">
            </div>
            <div class="reg-field">
                <label>{{ __('Requested Clock In') }} <span class="reg-req">*</span></label>
                <input type="time" name="requested_check_in" id="reg-in" class="form-control">
            </div>
            <div class="reg-field">
                <label>{{ __('Requested Clock Out') }} <span class="reg-req">*</span></label>
                <input type="time" name="requested_check_out" id="reg-out" class="form-control">
            </div>
            <div class="reg-field">
                <label>{{ __('Reason') }} <span class="reg-req">*</span></label>
                <textarea name="reason" id="reg-reason" rows="3" class="form-control" placeholder="{{ __('e.g. Forgot to clock in due to urgent meeting...') }}" required></textarea>
            </div>
            <div class="reg-modal-foot">
                <button type="button" class="btn btn-custom-default" data-reg-close>{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-custom">{{ __('Save') }}</button>
            </div>
        </form>
    </div>
</div>

{{-- View modal --}}
<div class="reg-modal-backdrop" id="reg-view-modal" hidden>
    <div class="reg-modal">
        <div class="reg-modal-head">
            <h5>{{ __('Regularization Details') }}</h5>
            <button type="button" class="reg-modal-x" data-reg-close>&times;</button>
        </div>
        <div class="reg-view-body">
            <p><strong>{{ __('Employee') }}:</strong> <span data-v="employee"></span></p>
            <p><strong>{{ __('Date') }}:</strong> <span data-v="date"></span> — <span data-v="status"></span></p>
            <p><strong>{{ __('Original') }}:</strong> <span data-v="oin"></span> → <span data-v="oout"></span></p>
            <p><strong>{{ __('Requested') }}:</strong> <span data-v="rin"></span> → <span data-v="rout"></span></p>
            <p><strong>{{ __('Reason') }}:</strong> <span data-v="reason"></span></p>
            <p data-v-remarks-wrap hidden><strong>{{ __('Review remarks') }}:</strong> <span data-v="remarks"></span></p>
        </div>
        <div class="reg-modal-foot"><button type="button" class="btn btn-custom-default" data-reg-close>{{ __('Close') }}</button></div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .reg-search { position: relative; min-width: 220px; }
    .reg-search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #98a2b3; }
    .reg-search input { padding-left: 34px; }
    .reg-status-filter, .reg-perpage { width: auto; min-width: 120px; }
    .reg-perpage { min-width: 72px; }

    .reg-stat { display: flex; align-items: center; justify-content: space-between; }
    .reg-stat-label { color: #667085; font-size: 13px; }
    .reg-stat-value { font-size: 28px; font-weight: 700; color: #1d2939; line-height: 1.1; }
    .reg-stat-sub { color: #98a2b3; font-size: 12px; }
    .reg-stat-icon { width: 44px; height: 44px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 18px; }
    .reg-stat-total    { background: #eef2ff; color: #4f46e5; }
    .reg-stat-pending  { background: #fef3c7; color: #d97706; }
    .reg-stat-approved { background: #dcfce7; color: #16a34a; }
    .reg-stat-rejected { background: #fee2e2; color: #ef4444; }

    .reg-card-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; }
    .reg-emp { display: flex; gap: 10px; align-items: center; }
    .reg-emp-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; background: #eef1f4; }
    .reg-emp-name { font-weight: 600; color: #1d2939; }
    .reg-emp-meta { font-size: 12px; color: #667085; display: flex; align-items: center; gap: 6px; }
    .reg-pill { font-size: 10.5px; padding: 1px 8px; border-radius: 20px; font-weight: 600; }
    .reg-pill-pending  { background: #fef3c7; color: #b45309; }
    .reg-pill-approved { background: #dcfce7; color: #15803d; }
    .reg-pill-rejected { background: #fee2e2; color: #b91c1c; }
    .reg-actions { display: flex; gap: 4px; flex-wrap: wrap; }
    .reg-actions form { display: inline; margin: 0; }
    .reg-act { border: none; background: transparent; cursor: pointer; padding: 2px 4px; font-size: 15px; }
    .reg-act-approve { color: #16a34a; }
    .reg-act-reject  { color: #ef4444; }
    .reg-act-view    { color: #3b82f6; }
    .reg-act-edit    { color: #f59e0b; }
    .reg-act-delete  { color: #ef4444; }

    .reg-times { display: flex; align-items: center; gap: 10px; margin-top: 14px; }
    .reg-times-col { flex: 1; }
    .reg-times-label { font-size: 11px; color: #98a2b3; text-transform: uppercase; letter-spacing: .3px; margin-bottom: 2px; }
    .reg-time { font-family: monospace; font-size: 13px; font-weight: 600; }
    .reg-time i { font-size: 11px; margin-right: 3px; }
    .reg-time-old { color: #ef4444; }
    .reg-time-new { color: #16a34a; }
    .reg-times-arrow { color: #cbd5e1; font-size: 16px; }

    .reg-reason { margin-top: 14px; }
    .reg-reason-label { font-size: 12px; color: #667085; margin-bottom: 2px; }
    .reg-reason-text { color: #344054; font-size: 13px; }
    .reg-foot { margin-top: 14px; padding-top: 10px; border-top: 1px solid #eef1f4; font-size: 12px; color: #98a2b3; }

    .reg-modal-backdrop { position: fixed; inset: 0; background: rgba(16,24,40,.45); display: flex; align-items: center; justify-content: center; z-index: 1080; padding: 20px; }
    .reg-modal-backdrop[hidden] { display: none; }
    .reg-modal { background: #fff; border-radius: 12px; width: 100%; max-width: 460px; box-shadow: 0 20px 40px rgba(0,0,0,.2); max-height: 90vh; overflow: auto; }
    .reg-modal-head { display: flex; justify-content: space-between; align-items: center; padding: 18px 22px; border-bottom: 1px solid #eef1f4; }
    .reg-modal-head h5 { margin: 0; font-weight: 700; }
    .reg-modal-x { border: none; background: transparent; font-size: 22px; line-height: 1; cursor: pointer; color: #98a2b3; }
    .reg-field { padding: 10px 22px 0; }
    .reg-field label { font-size: 13px; font-weight: 600; color: #344054; margin-bottom: 4px; display: block; }
    .reg-req { color: #ef4444; }
    .reg-modal-foot { display: flex; justify-content: flex-end; gap: 10px; padding: 18px 22px; }
    .reg-view-body { padding: 16px 22px; }
    .reg-view-body p { margin-bottom: 8px; font-size: 14px; color: #344054; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    const addModal = document.getElementById('reg-modal');
    const viewModal = document.getElementById('reg-view-modal');
    const form = document.getElementById('reg-form');
    const empSelect = document.getElementById('reg-employee');
    const recordSelect = document.getElementById('reg-record');
    const dateInput = document.getElementById('reg-date');
    const recordsUrlTpl = @json(route('attendance.regularizations.employee-records', ['employee' => '__ID__']));
    const storeUrl = @json(route('attendance.regularizations.store'));
    const updateUrlTpl = @json(url('attendance/regularizations')) + '/';

    const open = (m) => { m.hidden = false; };
    const close = () => { addModal.hidden = true; viewModal.hidden = true; };

    document.querySelectorAll('[data-reg-close]').forEach(b => b.addEventListener('click', close));
    [addModal, viewModal].forEach(m => m.addEventListener('click', e => { if (e.target === m) close(); }));

    async function loadRecords(employeeId, selectDate) {
        recordSelect.innerHTML = '<option value="">{{ __('Select attendance record') }}</option>';
        if (!employeeId) return;
        try {
            const res = await fetch(recordsUrlTpl.replace('__ID__', employeeId));
            const days = await res.json();
            days.forEach(d => {
                const o = document.createElement('option');
                o.value = d.date;
                o.dataset.in = d.check_in || '';
                o.dataset.out = d.check_out || '';
                o.textContent = d.date + '  (' + (d.check_in || '--:--') + ' → ' + (d.check_out || '--:--') + ')';
                if (d.date === selectDate) o.selected = true;
                recordSelect.appendChild(o);
            });
            if (selectDate) dateInput.value = selectDate;
        } catch (e) { /* ignore */ }
    }

    empSelect.addEventListener('change', () => loadRecords(empSelect.value, null));
    recordSelect.addEventListener('change', () => {
        dateInput.value = recordSelect.value;
        const opt = recordSelect.selectedOptions[0];
        if (opt && !document.getElementById('reg-in').value) document.getElementById('reg-in').value = opt.dataset.in || '';
        if (opt && !document.getElementById('reg-out').value) document.getElementById('reg-out').value = opt.dataset.out || '';
    });

    // Add
    document.querySelector('[data-reg-open-add]')?.addEventListener('click', () => {
        form.action = storeUrl;
        document.getElementById('reg-method').value = 'POST';
        document.getElementById('reg-modal-title').textContent = @json(__('Add New Regularization Request'));
        form.reset();
        empSelect.disabled = false;
        recordSelect.innerHTML = '<option value="">{{ __('Select attendance record') }}</option>';
        open(addModal);
    });

    // Edit
    document.querySelectorAll('[data-reg-edit]').forEach(btn => btn.addEventListener('click', async () => {
        const d = btn.dataset;
        form.action = updateUrlTpl + d.id;
        document.getElementById('reg-method').value = 'PUT';
        document.getElementById('reg-modal-title').textContent = @json(__('Edit Regularization Request'));
        // Employee is fixed on edit.
        for (const opt of empSelect.options) { if (opt.textContent.startsWith(d.employee)) { opt.selected = true; break; } }
        empSelect.disabled = true;
        await loadRecords(empSelect.value, d.date);
        document.getElementById('reg-in').value = d.rin || '';
        document.getElementById('reg-out').value = d.rout || '';
        document.getElementById('reg-reason').value = d.reason || '';
        open(addModal);
    }));

    // View
    document.querySelectorAll('[data-reg-view]').forEach(btn => btn.addEventListener('click', () => {
        const d = btn.dataset;
        const set = (k, v) => { const el = viewModal.querySelector('[data-v="' + k + '"]'); if (el) el.textContent = v || '—'; };
        set('employee', d.employee); set('date', d.date); set('status', d.status);
        set('oin', d.oin); set('oout', d.oout); set('rin', d.rin); set('rout', d.rout);
        set('reason', d.reason); set('remarks', d.remarks);
        viewModal.querySelector('[data-v-remarks-wrap]').hidden = !d.remarks;
        open(viewModal);
    }));
})();
</script>
@endpush

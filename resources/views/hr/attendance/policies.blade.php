@extends('layouts.backend')

@section('content')
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h1><i class="icon-shield"></i> {{ __('Attendance Policies') }}</h1>
        <button type="button" class="btn btn-custom" data-pol-add><i class="icon-plus"></i> {{ __('Add Attendance Policy') }}</button>
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            {{-- Toolbar --}}
            <div class="content_wrapper content-padded mb-3">
                <form method="GET" class="d-flex flex-wrap align-items-center gap-2">
                    <div class="ap-search flex-grow-1"><i class="icon-magnifier"></i>
                        <input type="text" name="search" value="{{ $filters['search'] }}" class="form-control" placeholder="{{ __('Search policies...') }}">
                    </div>
                    <button class="btn btn-custom" type="submit"><i class="icon-magnifier"></i> {{ __('Search') }}</button>
                    <select name="status" class="form-control ap-filter" onchange="this.form.submit()">
                        <option value="">{{ __('All Statuses') }}</option>
                        <option value="active" {{ $filters['status']==='active'?'selected':'' }}>{{ __('Active') }}</option>
                        <option value="inactive" {{ $filters['status']==='inactive'?'selected':'' }}>{{ __('Inactive') }}</option>
                    </select>
                    <div class="ms-auto d-flex align-items-center gap-2">
                        <span class="text-muted small">{{ __('Per Page') }}</span>
                        <select name="per_page" class="form-control ap-perpage" onchange="this.form.submit()">
                            @foreach([9,12,18,30] as $s)<option value="{{ $s }}" {{ (int)$filters['per_page']===$s?'selected':'' }}>{{ $s }}</option>@endforeach
                        </select>
                    </div>
                </form>
            </div>

            {{-- Stat cards --}}
            <div class="row g-3 mb-3">
                @php($cards = [
                    ['label'=>__('Total Policies'),   'value'=>$stats['total'],                          'icon'=>'icon-shield', 'cls'=>'ap-i-total'],
                    ['label'=>__('Active Policies'),  'value'=>$stats['active'],                         'icon'=>'icon-check',  'cls'=>'ap-i-active'],
                    ['label'=>__('Avg Late Grace'),   'value'=>$stats['avg_late_grace'].' '.__('min'),   'icon'=>'icon-clock',  'cls'=>'ap-i-grace'],
                    ['label'=>__('Avg Overtime Rate'),'value'=>'$'.number_format($stats['avg_overtime'],2),'icon'=>'icon-dollar','cls'=>'ap-i-ot'],
                ])
                @foreach($cards as $c)
                    <div class="col-md-3 col-sm-6"><div class="content_wrapper content-padded ap-stat">
                        <div><div class="ap-stat-label">{{ $c['label'] }}</div><div class="ap-stat-value">{{ $c['value'] }}</div></div>
                        <span class="ap-stat-icon {{ $c['cls'] }}"><i class="{{ $c['icon'] }}"></i></span>
                    </div></div>
                @endforeach
            </div>

            {{-- Policy cards --}}
            <div class="row g-3">
                @forelse($policies as $policy)
                    <div class="col-lg-6 col-xl-4"><div class="content_wrapper content-padded ap-card h-100">
                        <div class="ap-card-head">
                            <div class="ap-card-title">
                                <span class="ap-badge-icon"><i class="fa fa-shield"></i></span>
                                <div>
                                    <div class="ap-name">{{ $policy->name }}</div>
                                    <span class="ap-pill {{ $policy->isActive()?'ap-pill-active':'ap-pill-inactive' }}">{{ ucfirst($policy->status) }}</span>
                                </div>
                            </div>
                            <div class="ap-actions">
                                <button type="button" class="ap-act ap-view" title="{{ __('View') }}"
                                    data-pol-view data-name="{{ $policy->name }}" data-status="{{ ucfirst($policy->status) }}"
                                    data-late="{{ $policy->late_arrival_grace_minutes }}" data-early="{{ $policy->early_departure_grace_minutes }}"
                                    data-ot="{{ number_format($policy->overtime_rate_per_hour,2) }}" data-desc="{{ $policy->description }}"><i class="fa fa-eye"></i></button>
                                <button type="button" class="ap-act ap-edit" title="{{ __('Edit') }}"
                                    data-pol-edit data-id="{{ $policy->id }}" data-name="{{ $policy->name }}" data-desc="{{ $policy->description }}"
                                    data-late="{{ $policy->late_arrival_grace_minutes }}" data-early="{{ $policy->early_departure_grace_minutes }}"
                                    data-ot="{{ $policy->overtime_rate_per_hour }}" data-status="{{ $policy->status }}"><i class="fa fa-pencil"></i></button>
                                <form method="POST" action="{{ route('attendance.policies.status', $policy) }}">@csrf @method('PATCH')<button type="submit" class="ap-act ap-lock" title="{{ $policy->isActive()?__('Deactivate'):__('Activate') }}"><i class="fa {{ $policy->isActive()?'fa-unlock':'fa-lock' }}"></i></button></form>
                                <form method="POST" action="{{ route('attendance.policies.destroy', $policy) }}" onsubmit="return confirm('{{ __('Delete this policy?') }}');">@csrf @method('DELETE')<button type="submit" class="ap-act ap-del" title="{{ __('Delete') }}"><i class="fa fa-trash-o"></i></button></form>
                            </div>
                        </div>
                        <div class="ap-metrics">
                            <div class="ap-metric"><i class="fa fa-clock-o ap-ic-late"></i><div><div class="ap-metric-v">{{ $policy->late_arrival_grace_minutes }} {{ __('minutes') }}</div><div class="ap-metric-l">{{ __('Late Arrival Grace') }}</div></div></div>
                            <div class="ap-metric"><i class="fa fa-dollar ap-ic-ot"></i><div><div class="ap-metric-v">${{ number_format($policy->overtime_rate_per_hour,2) }}/hr</div><div class="ap-metric-l">{{ __('Overtime Rate') }}</div></div></div>
                            <div class="ap-metric"><i class="fa fa-clock-o ap-ic-early"></i><div><div class="ap-metric-v">{{ $policy->early_departure_grace_minutes }} {{ __('minutes') }}</div><div class="ap-metric-l">{{ __('Early Departure Grace') }}</div></div></div>
                        </div>
                        @if($policy->description)<div class="ap-desc">{{ $policy->description }}</div>@endif
                    </div></div>
                @empty
                    <div class="col-12"><div class="content_wrapper content-padded text-center text-muted py-5">{{ __('No attendance policies found.') }}</div></div>
                @endforelse
            </div>

            <div class="content_wrapper content-padded mt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="text-muted small">{{ __('Showing :from to :to of :total attendance policies', ['from'=>$policies->firstItem() ?? 0, 'to'=>$policies->lastItem() ?? 0, 'total'=>$policies->total()]) }}</span>
                {{ $policies->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
</div>

{{-- Add / Edit modal --}}
<div class="ap-modal-backdrop" id="ap-modal" hidden><div class="ap-modal">
    <div class="ap-modal-head"><h5 id="ap-modal-title">{{ __('Add New Attendance Policy') }}</h5><button type="button" class="ap-modal-x" data-pol-close>&times;</button></div>
    <form method="POST" id="ap-form" action="{{ route('attendance.policies.store') }}">
        @csrf<input type="hidden" name="_method" id="ap-method" value="POST">
        <div class="ap-field"><label>{{ __('Policy Name') }} <span class="ap-req">*</span></label><input type="text" name="name" id="ap-f-name" class="form-control" placeholder="{{ __('e.g. Standard Attendance Policy') }}" required></div>
        <div class="ap-field"><label>{{ __('Description') }}</label><textarea name="description" id="ap-f-desc" rows="3" class="form-control" placeholder="{{ __('e.g. Default attendance policy for all employees...') }}"></textarea></div>
        <div class="ap-field"><label>{{ __('Late Arrival Grace (minutes)') }} <span class="ap-req">*</span></label><input type="number" name="late_arrival_grace_minutes" id="ap-f-late" class="form-control" min="0" max="480" value="15" required></div>
        <div class="ap-field"><label>{{ __('Early Departure Grace (minutes)') }} <span class="ap-req">*</span></label><input type="number" name="early_departure_grace_minutes" id="ap-f-early" class="form-control" min="0" max="480" value="15" required></div>
        <div class="ap-field"><label>{{ __('Overtime Rate Per Hour') }} <span class="ap-req">*</span></label><input type="number" step="0.01" name="overtime_rate_per_hour" id="ap-f-ot" class="form-control" min="0" value="150" required></div>
        <div class="ap-field"><label>{{ __('Status') }} <span class="ap-req">*</span></label><select name="status" id="ap-f-status" class="form-control"><option value="active">{{ __('Active') }}</option><option value="inactive">{{ __('Inactive') }}</option></select></div>
        <div class="ap-modal-foot"><button type="button" class="btn btn-custom-default" data-pol-close>{{ __('Cancel') }}</button><button type="submit" class="btn btn-custom">{{ __('Save') }}</button></div>
    </form>
</div></div>

{{-- View modal --}}
<div class="ap-modal-backdrop" id="ap-view-modal" hidden><div class="ap-modal">
    <div class="ap-modal-head"><h5>{{ __('Policy Details') }}</h5><button type="button" class="ap-modal-x" data-pol-close>&times;</button></div>
    <div class="ap-view-body">
        <p><strong>{{ __('Name') }}:</strong> <span data-v="name"></span> — <span data-v="status"></span></p>
        <p><strong>{{ __('Late Arrival Grace') }}:</strong> <span data-v="late"></span> {{ __('minutes') }}</p>
        <p><strong>{{ __('Early Departure Grace') }}:</strong> <span data-v="early"></span> {{ __('minutes') }}</p>
        <p><strong>{{ __('Overtime Rate') }}:</strong> $<span data-v="ot"></span>/hr</p>
        <p><strong>{{ __('Description') }}:</strong> <span data-v="desc"></span></p>
    </div>
    <div class="ap-modal-foot"><button type="button" class="btn btn-custom-default" data-pol-close>{{ __('Close') }}</button></div>
</div></div>
@endsection

@push('styles')
<style>
    .ap-search { position: relative; min-width: 220px; }
    .ap-search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #98a2b3; }
    .ap-search input { padding-left: 34px; }
    .ap-filter, .ap-perpage { width: auto; min-width: 120px; }
    .ap-perpage { min-width: 72px; }
    .ap-stat { display: flex; align-items: center; justify-content: space-between; }
    .ap-stat-label { color: #667085; font-size: 13px; }
    .ap-stat-value { font-size: 26px; font-weight: 700; color: #1d2939; line-height: 1.15; }
    .ap-stat-icon { width: 44px; height: 44px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 18px; }
    .ap-i-total { background: #eef2ff; color: #64748b; } .ap-i-active { background: #dcfce7; color: #16a34a; }
    .ap-i-grace { background: #fef3c7; color: #d97706; } .ap-i-ot { background: #dcfce7; color: #16a34a; }
    .ap-card-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; }
    .ap-card-title { display: flex; gap: 10px; }
    .ap-badge-icon { width: 34px; height: 34px; border-radius: 8px; background: #eef2ff; color: #6366f1; display: inline-flex; align-items: center; justify-content: center; }
    .ap-name { font-weight: 600; color: #1d2939; }
    .ap-pill { font-size: 10.5px; padding: 1px 8px; border-radius: 20px; font-weight: 600; }
    .ap-pill-active { background: #dcfce7; color: #15803d; } .ap-pill-inactive { background: #f1f5f9; color: #64748b; }
    .ap-actions { display: flex; gap: 3px; } .ap-actions form { display: inline; margin: 0; }
    .ap-act { border: none; background: transparent; cursor: pointer; padding: 2px 4px; font-size: 15px; }
    .ap-view { color: #3b82f6; } .ap-edit { color: #f59e0b; } .ap-lock { color: #6b7280; } .ap-del { color: #ef4444; }
    .ap-metrics { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 16px; }
    .ap-metric { display: flex; gap: 8px; align-items: flex-start; }
    .ap-metric i { margin-top: 2px; } .ap-ic-late { color: #f59e0b; } .ap-ic-ot { color: #16a34a; } .ap-ic-early { color: #3b82f6; }
    .ap-metric-v { font-weight: 600; color: #1d2939; font-size: 14px; } .ap-metric-l { font-size: 12px; color: #98a2b3; }
    .ap-desc { margin-top: 16px; padding-top: 12px; border-top: 1px solid #eef1f4; color: #667085; font-size: 13px; }
    .ap-modal-backdrop { position: fixed; inset: 0; background: rgba(16,24,40,.45); display: flex; align-items: center; justify-content: center; z-index: 1080; padding: 20px; }
    .ap-modal-backdrop[hidden] { display: none; }
    .ap-modal { background: #fff; border-radius: 12px; width: 100%; max-width: 460px; box-shadow: 0 20px 40px rgba(0,0,0,.2); max-height: 90vh; overflow: auto; }
    .ap-modal-head { display: flex; justify-content: space-between; align-items: center; padding: 18px 22px; border-bottom: 1px solid #eef1f4; }
    .ap-modal-head h5 { margin: 0; font-weight: 700; }
    .ap-modal-x { border: none; background: transparent; font-size: 22px; cursor: pointer; color: #98a2b3; }
    .ap-field { padding: 10px 22px 0; } .ap-field label { font-size: 13px; font-weight: 600; color: #344054; margin-bottom: 4px; display: block; }
    .ap-req { color: #ef4444; } .ap-modal-foot { display: flex; justify-content: flex-end; gap: 10px; padding: 18px 22px; }
    .ap-view-body { padding: 16px 22px; } .ap-view-body p { margin-bottom: 8px; font-size: 14px; color: #344054; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    const modal = document.getElementById('ap-modal'), viewModal = document.getElementById('ap-view-modal'), form = document.getElementById('ap-form');
    const storeUrl = @json(route('attendance.policies.store')), updateBase = @json(url('attendance/policies')) + '/';
    const open = m => m.hidden = false, close = () => { modal.hidden = true; viewModal.hidden = true; };
    document.querySelectorAll('[data-pol-close]').forEach(b => b.addEventListener('click', close));
    [modal, viewModal].forEach(m => m.addEventListener('click', e => { if (e.target === m) close(); }));

    document.querySelector('[data-pol-add]')?.addEventListener('click', () => {
        form.action = storeUrl; document.getElementById('ap-method').value = 'POST';
        document.getElementById('ap-modal-title').textContent = @json(__('Add New Attendance Policy'));
        form.reset(); document.getElementById('ap-f-late').value = 15; document.getElementById('ap-f-early').value = 15; document.getElementById('ap-f-ot').value = 150;
        open(modal);
    });
    document.querySelectorAll('[data-pol-edit]').forEach(btn => btn.addEventListener('click', () => {
        const d = btn.dataset; form.action = updateBase + d.id; document.getElementById('ap-method').value = 'PUT';
        document.getElementById('ap-modal-title').textContent = @json(__('Edit Attendance Policy'));
        document.getElementById('ap-f-name').value = d.name || ''; document.getElementById('ap-f-desc').value = d.desc || '';
        document.getElementById('ap-f-late').value = d.late; document.getElementById('ap-f-early').value = d.early;
        document.getElementById('ap-f-ot').value = d.ot; document.getElementById('ap-f-status').value = d.status;
        open(modal);
    }));
    document.querySelectorAll('[data-pol-view]').forEach(btn => btn.addEventListener('click', () => {
        const d = btn.dataset, set = (k,v) => { const el = viewModal.querySelector('[data-v="'+k+'"]'); if (el) el.textContent = v || '—'; };
        set('name', d.name); set('status', d.status); set('late', d.late); set('early', d.early); set('ot', d.ot); set('desc', d.desc);
        open(viewModal);
    }));
})();
</script>
@endpush

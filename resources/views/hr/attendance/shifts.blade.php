@extends('layouts.backend')

@section('content')
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h1><i class="icon-clock"></i> {{ __('Shifts') }}</h1>
        <button type="button" class="btn btn-custom" data-sh-add><i class="icon-plus"></i> {{ __('Add Shift') }}</button>
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            {{-- Toolbar --}}
            <div class="content_wrapper content-padded mb-3">
                <form method="GET" class="d-flex flex-wrap align-items-center gap-2">
                    <div class="sh-search flex-grow-1"><i class="icon-magnifier"></i>
                        <input type="text" name="search" value="{{ $filters['search'] }}" class="form-control" placeholder="{{ __('Search shifts...') }}">
                    </div>
                    <button class="btn btn-custom" type="submit"><i class="icon-magnifier"></i> {{ __('Search') }}</button>
                    <select name="status" class="form-control sh-filter" onchange="this.form.submit()">
                        <option value="">{{ __('All Statuses') }}</option>
                        <option value="active" {{ $filters['status']==='active'?'selected':'' }}>{{ __('Active') }}</option>
                        <option value="inactive" {{ $filters['status']==='inactive'?'selected':'' }}>{{ __('Inactive') }}</option>
                    </select>
                    <div class="ms-auto d-flex align-items-center gap-2">
                        <span class="text-muted small">{{ __('Per Page') }}</span>
                        <select name="per_page" class="form-control sh-perpage" onchange="this.form.submit()">
                            @foreach([9,12,18,30] as $s)<option value="{{ $s }}" {{ (int)$filters['per_page']===$s?'selected':'' }}>{{ $s }}</option>@endforeach
                        </select>
                    </div>
                </form>
            </div>

            {{-- Stat cards --}}
            <div class="row g-3 mb-3">
                @php($cards = [
                    ['label'=>__('Total Shifts'),  'value'=>$stats['total'],  'icon'=>'icon-people',        'cls'=>'sh-i-total'],
                    ['label'=>__('Active Shifts'), 'value'=>$stats['active'], 'icon'=>'fa fa-sun-o',        'cls'=>'sh-i-active'],
                    ['label'=>__('Night Shifts'),  'value'=>$stats['night'],  'icon'=>'fa fa-moon-o',       'cls'=>'sh-i-night'],
                    ['label'=>__('Day Shifts'),    'value'=>$stats['day'],    'icon'=>'fa fa-sun-o',        'cls'=>'sh-i-day'],
                ])
                @foreach($cards as $c)
                    <div class="col-md-3 col-sm-6"><div class="content_wrapper content-padded sh-stat">
                        <div><div class="sh-stat-label">{{ $c['label'] }}</div><div class="sh-stat-value">{{ $c['value'] }}</div></div>
                        <span class="sh-stat-icon {{ $c['cls'] }}"><i class="{{ $c['icon'] }}"></i></span>
                    </div></div>
                @endforeach
            </div>

            {{-- Shift cards --}}
            <div class="row g-3">
                @forelse($shifts as $shift)
                    <div class="col-lg-6 col-xl-4"><div class="content_wrapper content-padded sh-card h-100">
                        <div class="sh-card-head">
                            <div class="sh-card-title">
                                <span class="sh-badge-icon"><i class="fa {{ $shift->is_night_shift?'fa-moon-o':'fa-sun-o' }}"></i></span>
                                <div>
                                    <div class="sh-name">{{ $shift->name }}</div>
                                    <div class="d-flex gap-1 mt-1">
                                        <span class="sh-pill {{ $shift->is_night_shift?'sh-pill-night':'sh-pill-day' }}">{{ $shift->is_night_shift?__('Night Shift'):__('Day Shift') }}</span>
                                        <span class="sh-pill {{ $shift->isActive()?'sh-pill-active':'sh-pill-inactive' }}">{{ ucfirst($shift->status) }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="sh-actions">
                                <button type="button" class="sh-act sh-view" title="{{ __('View') }}"
                                    data-sh-view data-name="{{ $shift->name }}" data-hours="{{ $shift->hoursLabel() }}"
                                    data-break="{{ $shift->break_duration_minutes }}" data-working="{{ $shift->workingHours() }}"
                                    data-grace="{{ $shift->grace_period_minutes }}" data-type="{{ $shift->is_night_shift?__('Night Shift'):__('Day Shift') }}"
                                    data-status="{{ ucfirst($shift->status) }}" data-desc="{{ $shift->description }}"><i class="fa fa-eye"></i></button>
                                <button type="button" class="sh-act sh-edit" title="{{ __('Edit') }}"
                                    data-sh-edit data-id="{{ $shift->id }}" data-name="{{ $shift->name }}" data-desc="{{ $shift->description }}"
                                    data-start="{{ \Illuminate\Support\Carbon::parse($shift->start_time)->format('H:i') }}" data-end="{{ \Illuminate\Support\Carbon::parse($shift->end_time)->format('H:i') }}"
                                    data-break="{{ $shift->break_duration_minutes }}"
                                    data-bstart="{{ $shift->break_start_time ? \Illuminate\Support\Carbon::parse($shift->break_start_time)->format('H:i') : '' }}"
                                    data-bend="{{ $shift->break_end_time ? \Illuminate\Support\Carbon::parse($shift->break_end_time)->format('H:i') : '' }}"
                                    data-grace="{{ $shift->grace_period_minutes }}" data-night="{{ $shift->is_night_shift?1:0 }}" data-status="{{ $shift->status }}"><i class="fa fa-pencil"></i></button>
                                <form method="POST" action="{{ route('attendance.shifts.status', $shift) }}">@csrf @method('PATCH')<button type="submit" class="sh-act sh-lock" title="{{ $shift->isActive()?__('Deactivate'):__('Activate') }}"><i class="fa {{ $shift->isActive()?'fa-unlock':'fa-lock' }}"></i></button></form>
                                <form method="POST" action="{{ route('attendance.shifts.destroy', $shift) }}" onsubmit="return confirm('{{ __('Delete this shift?') }}');">@csrf @method('DELETE')<button type="submit" class="sh-act sh-del" title="{{ __('Delete') }}"><i class="fa fa-trash-o"></i></button></form>
                            </div>
                        </div>
                        <div class="sh-metrics">
                            <div class="sh-metric"><i class="fa fa-clock-o sh-ic-hours"></i><div><div class="sh-metric-v">{{ $shift->hoursLabel() }}</div><div class="sh-metric-l">{{ __('Shift Hours') }}</div></div></div>
                            <div class="sh-metric"><i class="fa fa-coffee sh-ic-break"></i><div><div class="sh-metric-v">{{ $shift->break_duration_minutes }} {{ __('minutes') }}</div><div class="sh-metric-l">{{ __('Break Duration') }}</div></div></div>
                            <div class="sh-metric"><i class="fa fa-calendar-o sh-ic-work"></i><div><div class="sh-metric-v">{{ number_format($shift->workingHours(),1) }} {{ __('hours') }}</div><div class="sh-metric-l">{{ __('Working Time') }}</div></div></div>
                            <div class="sh-metric"><i class="fa fa-hourglass-half sh-ic-grace"></i><div><div class="sh-metric-v">{{ $shift->grace_period_minutes }} {{ __('minutes') }}</div><div class="sh-metric-l">{{ __('Grace Period') }}</div></div></div>
                        </div>
                        @if($shift->description)<div class="sh-desc">{{ $shift->description }}</div>@endif
                    </div></div>
                @empty
                    <div class="col-12"><div class="content_wrapper content-padded text-center text-muted py-5">{{ __('No shifts found.') }}</div></div>
                @endforelse
            </div>

            <div class="content_wrapper content-padded mt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="text-muted small">{{ __('Showing :from to :to of :total shifts', ['from'=>$shifts->firstItem() ?? 0, 'to'=>$shifts->lastItem() ?? 0, 'total'=>$shifts->total()]) }}</span>
                {{ $shifts->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
</div>

{{-- Add / Edit modal --}}
<div class="sh-modal-backdrop" id="sh-modal" hidden><div class="sh-modal">
    <div class="sh-modal-head"><h5 id="sh-modal-title">{{ __('Add New Shift') }}</h5><button type="button" class="sh-modal-x" data-sh-close>&times;</button></div>
    <form method="POST" id="sh-form" action="{{ route('attendance.shifts.store') }}">
        @csrf<input type="hidden" name="_method" id="sh-method" value="POST">
        <div class="sh-field"><label>{{ __('Shift Name') }} <span class="sh-req">*</span></label><input type="text" name="name" id="sh-f-name" class="form-control" placeholder="{{ __('e.g. Morning Shift') }}" required></div>
        <div class="sh-field"><label>{{ __('Description') }}</label><textarea name="description" id="sh-f-desc" rows="2" class="form-control" placeholder="{{ __('e.g. Standard morning shift for office staff...') }}"></textarea></div>
        <div class="sh-field"><label>{{ __('Start Time') }} <span class="sh-req">*</span></label><input type="time" name="start_time" id="sh-f-start" class="form-control" required></div>
        <div class="sh-field"><label>{{ __('End Time') }} <span class="sh-req">*</span></label><input type="time" name="end_time" id="sh-f-end" class="form-control" required></div>
        <div class="sh-field"><label>{{ __('Break Duration (minutes)') }} <span class="sh-req">*</span></label><input type="number" name="break_duration_minutes" id="sh-f-break" class="form-control" min="0" max="480" value="60" required></div>
        <div class="sh-field"><label>{{ __('Break Start Time') }}</label><input type="time" name="break_start_time" id="sh-f-bstart" class="form-control"></div>
        <div class="sh-field"><label>{{ __('Break End Time') }}</label><input type="time" name="break_end_time" id="sh-f-bend" class="form-control"></div>
        <div class="sh-field"><label>{{ __('Grace Period (minutes)') }} <span class="sh-req">*</span></label><input type="number" name="grace_period_minutes" id="sh-f-grace" class="form-control" min="0" max="480" value="15" required></div>
        <div class="sh-field"><label class="sh-check"><input type="checkbox" name="is_night_shift" id="sh-f-night" value="1"> {{ __('Night Shift') }}</label></div>
        <div class="sh-field"><label>{{ __('Status') }} <span class="sh-req">*</span></label><select name="status" id="sh-f-status" class="form-control"><option value="active">{{ __('Active') }}</option><option value="inactive">{{ __('Inactive') }}</option></select></div>
        <div class="sh-modal-foot"><button type="button" class="btn btn-custom-default" data-sh-close>{{ __('Cancel') }}</button><button type="submit" class="btn btn-custom">{{ __('Save') }}</button></div>
    </form>
</div></div>

{{-- View modal --}}
<div class="sh-modal-backdrop" id="sh-view-modal" hidden><div class="sh-modal">
    <div class="sh-modal-head"><h5>{{ __('Shift Details') }}</h5><button type="button" class="sh-modal-x" data-sh-close>&times;</button></div>
    <div class="sh-view-body">
        <p><strong>{{ __('Name') }}:</strong> <span data-v="name"></span> — <span data-v="type"></span> / <span data-v="status"></span></p>
        <p><strong>{{ __('Shift Hours') }}:</strong> <span data-v="hours"></span></p>
        <p><strong>{{ __('Working Time') }}:</strong> <span data-v="working"></span> {{ __('hours') }}</p>
        <p><strong>{{ __('Break Duration') }}:</strong> <span data-v="break"></span> {{ __('minutes') }}</p>
        <p><strong>{{ __('Grace Period') }}:</strong> <span data-v="grace"></span> {{ __('minutes') }}</p>
        <p><strong>{{ __('Description') }}:</strong> <span data-v="desc"></span></p>
    </div>
    <div class="sh-modal-foot"><button type="button" class="btn btn-custom-default" data-sh-close>{{ __('Close') }}</button></div>
</div></div>
@endsection

@push('styles')
<style>
    .sh-search { position: relative; min-width: 220px; }
    .sh-search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #98a2b3; }
    .sh-search input { padding-left: 34px; }
    .sh-filter, .sh-perpage { width: auto; min-width: 120px; } .sh-perpage { min-width: 72px; }
    .sh-stat { display: flex; align-items: center; justify-content: space-between; }
    .sh-stat-label { color: #667085; font-size: 13px; } .sh-stat-value { font-size: 26px; font-weight: 700; color: #1d2939; line-height: 1.15; }
    .sh-stat-icon { width: 44px; height: 44px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 18px; }
    .sh-i-total { background: #f1f5f9; color: #64748b; } .sh-i-active { background: #dcfce7; color: #16a34a; }
    .sh-i-night { background: #eef2ff; color: #6366f1; } .sh-i-day { background: #dbeafe; color: #3b82f6; }
    .sh-card-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; }
    .sh-card-title { display: flex; gap: 10px; }
    .sh-badge-icon { width: 34px; height: 34px; border-radius: 8px; background: #f1f5f9; color: #475569; display: inline-flex; align-items: center; justify-content: center; }
    .sh-name { font-weight: 600; color: #1d2939; }
    .sh-pill { font-size: 10.5px; padding: 1px 8px; border-radius: 20px; font-weight: 600; }
    .sh-pill-day { background: #fef3c7; color: #b45309; } .sh-pill-night { background: #e0e7ff; color: #4338ca; }
    .sh-pill-active { background: #dcfce7; color: #15803d; } .sh-pill-inactive { background: #f1f5f9; color: #64748b; }
    .sh-actions { display: flex; gap: 3px; } .sh-actions form { display: inline; margin: 0; }
    .sh-act { border: none; background: transparent; cursor: pointer; padding: 2px 4px; font-size: 15px; }
    .sh-view { color: #3b82f6; } .sh-edit { color: #f59e0b; } .sh-lock { color: #6b7280; } .sh-del { color: #ef4444; }
    .sh-metrics { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 16px; }
    .sh-metric { display: flex; gap: 8px; align-items: flex-start; } .sh-metric i { margin-top: 2px; }
    .sh-ic-hours { color: #f59e0b; } .sh-ic-break { color: #f59e0b; } .sh-ic-work { color: #16a34a; } .sh-ic-grace { color: #f59e0b; }
    .sh-metric-v { font-weight: 600; color: #1d2939; font-size: 14px; } .sh-metric-l { font-size: 12px; color: #98a2b3; }
    .sh-desc { margin-top: 16px; padding-top: 12px; border-top: 1px solid #eef1f4; color: #667085; font-size: 13px; }
    .sh-modal-backdrop { position: fixed; inset: 0; background: rgba(16,24,40,.45); display: flex; align-items: center; justify-content: center; z-index: 1080; padding: 20px; }
    .sh-modal-backdrop[hidden] { display: none; }
    .sh-modal { background: #fff; border-radius: 12px; width: 100%; max-width: 460px; box-shadow: 0 20px 40px rgba(0,0,0,.2); max-height: 90vh; overflow: auto; }
    .sh-modal-head { display: flex; justify-content: space-between; align-items: center; padding: 18px 22px; border-bottom: 1px solid #eef1f4; }
    .sh-modal-head h5 { margin: 0; font-weight: 700; } .sh-modal-x { border: none; background: transparent; font-size: 22px; cursor: pointer; color: #98a2b3; }
    .sh-field { padding: 10px 22px 0; } .sh-field label { font-size: 13px; font-weight: 600; color: #344054; margin-bottom: 4px; display: block; }
    .sh-check { display: flex; align-items: center; gap: 8px; } .sh-check input { width: auto; }
    .sh-req { color: #ef4444; } .sh-modal-foot { display: flex; justify-content: flex-end; gap: 10px; padding: 18px 22px; }
    .sh-view-body { padding: 16px 22px; } .sh-view-body p { margin-bottom: 8px; font-size: 14px; color: #344054; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    const modal = document.getElementById('sh-modal'), viewModal = document.getElementById('sh-view-modal'), form = document.getElementById('sh-form');
    const storeUrl = @json(route('attendance.shifts.store')), updateBase = @json(url('attendance/shifts')) + '/';
    const open = m => m.hidden = false, close = () => { modal.hidden = true; viewModal.hidden = true; };
    const g = id => document.getElementById(id);
    document.querySelectorAll('[data-sh-close]').forEach(b => b.addEventListener('click', close));
    [modal, viewModal].forEach(m => m.addEventListener('click', e => { if (e.target === m) close(); }));

    document.querySelector('[data-sh-add]')?.addEventListener('click', () => {
        form.action = storeUrl; g('sh-method').value = 'POST';
        g('sh-modal-title').textContent = @json(__('Add New Shift'));
        form.reset(); g('sh-f-break').value = 60; g('sh-f-grace').value = 15;
        open(modal);
    });
    document.querySelectorAll('[data-sh-edit]').forEach(btn => btn.addEventListener('click', () => {
        const d = btn.dataset; form.action = updateBase + d.id; g('sh-method').value = 'PUT';
        g('sh-modal-title').textContent = @json(__('Edit Shift'));
        g('sh-f-name').value = d.name || ''; g('sh-f-desc').value = d.desc || '';
        g('sh-f-start').value = d.start; g('sh-f-end').value = d.end; g('sh-f-break').value = d.break;
        g('sh-f-bstart').value = d.bstart || ''; g('sh-f-bend').value = d.bend || ''; g('sh-f-grace').value = d.grace;
        g('sh-f-night').checked = d.night === '1'; g('sh-f-status').value = d.status;
        open(modal);
    }));
    document.querySelectorAll('[data-sh-view]').forEach(btn => btn.addEventListener('click', () => {
        const d = btn.dataset, set = (k,v) => { const el = viewModal.querySelector('[data-v="'+k+'"]'); if (el) el.textContent = v || '—'; };
        set('name', d.name); set('type', d.type); set('status', d.status); set('hours', d.hours);
        set('working', d.working); set('break', d.break); set('grace', d.grace); set('desc', d.desc);
        open(viewModal);
    }));
})();
</script>
@endpush

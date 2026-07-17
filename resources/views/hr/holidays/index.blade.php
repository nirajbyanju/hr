@extends('layouts.backend')

@section('content')
<div class="wrapper-page">
    <div class="page-title holiday-page-title d-flex justify-content-between align-items-center">
        <div>
            <span class="holiday-eyebrow">{{ __('Holiday planner') }}</span>
            <h1><i class="icon-calendar"></i> {{ __('Holidays') }}</h1>
            <p class="mb-0 text-muted">{{ __('Plan public holidays and weekly days off in one place.') }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('holidays.export-current-year', ['year' => $year]) }}" class="btn btn-custom-default">
                <i class="icon-cloud-download"></i> {{ __('Export Excel (CSV)') }}
            </a>
            @if(auth()->user()?->hasPermission('holiday.create'))
                <a href="{{ route('holidays.create') }}" class="btn btn-custom"><i class="icon-plus"></i> {{ __('Add Holiday') }}</a>
            @endif
        </div>
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            <div>
                <div class="content_wrapper content-padded">
                    <div class="holiday-toolbar">
                        <div class="holiday-toolbar-copy">
                            <span class="holiday-toolbar-label">{{ __('Calendar year') }}</span>
                            <strong>{{ $year }}</strong>
                        </div>
                        <form method="GET" class="holiday-year-form">
                            <select name="year" class="form-control" aria-label="{{ __('Calendar year') }}">
                                @foreach($availableYears as $yearOption)
                                    <option value="{{ $yearOption }}" {{ (int) $year === (int) $yearOption ? 'selected' : '' }}>{{ $yearOption }}</option>
                                @endforeach
                            </select>
                            <input type="hidden" name="per_page" value="{{ $perPage }}">
                            <button class="btn btn-custom" type="submit">{{ __('Go') }}</button>
                        </form>
                    </div>

                    <ul class="nav holiday-tab-nav mb-3" id="holidayTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="holiday-table-tab" data-bs-toggle="tab" data-bs-target="#holiday-table-pane" type="button" role="tab" aria-controls="holiday-table-pane" aria-selected="false">
                                <i class="icon-list"></i> {{ __('List') }}
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="holiday-calendar-tab" data-bs-toggle="tab" data-bs-target="#holiday-calendar-pane" type="button" role="tab" aria-controls="holiday-calendar-pane" aria-selected="true">
                                <i class="icon-calendar"></i> {{ __('Calendar') }}
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <div class="tab-pane fade" id="holiday-table-pane" role="tabpanel" aria-labelledby="holiday-table-tab">
                            <form method="GET" class="row g-2 mb-3">
                                <div class="col-md-3">
                                    <input type="hidden" name="year" value="{{ $year }}">
                                    <select name="per_page" class="form-control">
                                        @foreach([10,20,50,100] as $size)
                                            <option value="{{ $size }}" {{ (int) $perPage === $size ? 'selected' : '' }}>{{ $size }} / page</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4 d-flex gap-2">
                                    <button class="btn btn-custom" type="submit"><i class="icon-magnifier"></i> {{ __('Apply') }}</button>
                                    <a href="{{ route('holidays.index') }}" class="btn btn-custom-default"><i class="icon-refresh"></i></a>
                                </div>
                            </form>

                            <div class="table-responsive">
                                <table class="table table-bordered align-middle">
                                    <thead>
                                        <tr>
                                            <th>{{ __('Date') }}</th>
                                            <th>{{ __('Day') }}</th>
                                            <th>{{ __('Title') }}</th>
                                            <th>{{ __('Type') }}</th>
                                            <th>{{ __('Optional') }}</th>
                                            <th>{{ __('Description') }}</th>
                                            <th>{{ __('Actions') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($holidays as $holiday)
                                            <tr>
                                                <td>{{ $holiday->holiday_date?->format('d M Y') }}</td>
                                                <td>{{ $holiday->holiday_date?->format('l') }}</td>
                                                <td>{{ $holiday->title }}</td>
                                                <td><span class="holiday-badge holiday-badge-type">{{ __(ucfirst($holiday->holiday_type)) }}</span></td>
                                                <td>
                                                    @if($holiday->is_optional)
                                                        <span class="holiday-badge holiday-badge-optional">{{ __('Yes') }}</span>
                                                    @else
                                                        <span class="holiday-badge holiday-badge-regular">{{ __('No') }}</span>
                                                    @endif
                                                </td>
                                                <td>{{ $holiday->description ?: '-' }}</td>
                                                <td class="action-buttons">
                                                    @if(auth()->user()?->hasPermission('holiday.update'))
                                                        <a href="{{ route('holidays.edit', $holiday) }}" title="{{ __('Edit Holiday') }}">
                                                            <i class="icon-pencil"></i>
                                                        </a>
                                                    @endif
                                                    @if(auth()->user()?->hasPermission('holiday.delete'))
                                                        <form method="POST" action="{{ route('holidays.destroy', $holiday) }}" class="d-inline" onsubmit="return confirm('Delete this holiday?');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" title="{{ __('Delete Holiday') }}"><i class="icon-trash"></i></button>
                                                        </form>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="text-center">No holidays configured for {{ $year }}.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            {{ $holidays->links('pagination::bootstrap-5') }}
                        </div>

                        <div class="tab-pane fade show active" id="holiday-calendar-pane" role="tabpanel" aria-labelledby="holiday-calendar-tab">
                            <div class="holiday-calendar-shell">
                                <div class="holiday-calendar-topbar">
                                    <div>
                                        <span class="holiday-calendar-kicker">{{ __('Gregorian & Bikram Sambat') }}</span>
                                        <h4 class="mb-0" id="holiday-calendar-month-label"></h4>
                                        <span class="holiday-nepali-month" id="holiday-nepali-month-label"></span>
                                    </div>
                                    <div class="holiday-calendar-actions">
                                        <button class="btn btn-custom-default" type="button" id="holiday-calendar-prev" aria-label="{{ __('Previous month') }}">
                                    <i class="icon-arrow-left"></i> {{ __('Previous') }}
                                        </button>
                                        <button class="btn btn-custom-default" type="button" id="holiday-calendar-next" aria-label="{{ __('Next month') }}">
                                            {{ __('Next') }} <i class="icon-arrow-right"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="holiday-calendar-meta">
                                    <span><i class="icon-info"></i> {{ __('Select a highlighted day to see holiday details.') }}</span>
                                    <div class="holiday-calendar-legend">
                                        <span><i class="holiday-legend-dot holiday"></i>{{ __('Holiday') }}</span>
                                        <span><i class="holiday-legend-dot weekend"></i>{{ __('Weekend') }}</span>
                                        <span><i class="holiday-legend-dot today"></i>{{ __('Today') }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="holiday-calendar-grid" id="holiday-calendar-grid"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade holiday-modal" id="holidayDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('Holiday Details') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body" id="holiday-details-body"></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const holidays = @json($calendarItems);
        const weekendDayIndexes = @json($weekendDayIndexes);
        const weekendSet = new Set((Array.isArray(weekendDayIndexes) ? weekendDayIndexes : []).map((value) => Number(value)));
        const holidaysByDate = holidays.reduce((carry, item) => {
            if (!carry[item.holiday_date]) {
                carry[item.holiday_date] = [];
            }
            carry[item.holiday_date].push(item);
            return carry;
        }, {});

        const weekDays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        const monthNames = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'
        ];

        const calendarGrid = document.getElementById('holiday-calendar-grid');
        const monthLabel = document.getElementById('holiday-calendar-month-label');
        const nepaliMonthLabel = document.getElementById('holiday-nepali-month-label');
        const prevButton = document.getElementById('holiday-calendar-prev');
        const nextButton = document.getElementById('holiday-calendar-next');
        const detailsModalEl = document.getElementById('holidayDetailsModal');
        const detailsBody = document.getElementById('holiday-details-body');
        const detailsModal = detailsModalEl ? new bootstrap.Modal(detailsModalEl) : null;
        const nepaliDateFormatter = (() => {
            try {
                return new Intl.DateTimeFormat('ne-NP-u-ca-bikram-sambat', {
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric',
                });
            } catch (error) {
                return new Intl.DateTimeFormat('ne-NP', { day: 'numeric', month: 'long', year: 'numeric' });
            }
        })();

        if (!calendarGrid || !monthLabel || !prevButton || !nextButton) {
            return;
        }

        const initialYear = Number(@json($year));
        let currentMonthDate = new Date(initialYear, new Date().getMonth(), 1);
        if (currentMonthDate.getFullYear() !== initialYear) {
            currentMonthDate = new Date(initialYear, 0, 1);
        }

        const escapeHtml = (value) => String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');

        const formatNepaliDate = (date) => nepaliDateFormatter.format(date);

        const renderCalendar = () => {
            const year = currentMonthDate.getFullYear();
            const month = currentMonthDate.getMonth();
            const firstDay = new Date(year, month, 1);
            const startWeekDay = firstDay.getDay();
            const totalDays = new Date(year, month + 1, 0).getDate();

            monthLabel.textContent = `${monthNames[month]} ${year}`;
            if (nepaliMonthLabel) {
                nepaliMonthLabel.textContent = `वि.सं. ${formatNepaliDate(new Date(year, month, 15))}`;
            }
            calendarGrid.innerHTML = '';

            weekDays.forEach((day) => {
                const header = document.createElement('div');
                header.className = 'holiday-calendar-cell header';
                header.textContent = day;
                calendarGrid.appendChild(header);
            });

            for (let i = 0; i < startWeekDay; i += 1) {
                const emptyCell = document.createElement('div');
                emptyCell.className = 'holiday-calendar-cell empty';
                calendarGrid.appendChild(emptyCell);
            }

            for (let day = 1; day <= totalDays; day += 1) {
                const dateKey = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                const dayHolidays = holidaysByDate[dateKey] || [];
                const dayOfWeek = new Date(year, month, day).getDay();
                const isWeekend = weekendSet.has(dayOfWeek);
                const calendarDate = new Date(year, month, day);
                const isToday = dateKey === new Date().toISOString().slice(0, 10);
                const dayCell = document.createElement('div');
                dayCell.className = `holiday-calendar-cell day ${isWeekend ? 'weekend' : ''} ${dayHolidays.length > 0 ? 'has-holiday' : ''} ${isToday ? 'today' : ''}`;
                dayCell.dataset.date = dateKey;

                const dateHeader = document.createElement('div');
                dateHeader.className = 'holiday-date-header';
                const number = document.createElement('span');
                number.className = 'holiday-day-number';
                number.textContent = String(day);
                const nepaliDate = document.createElement('span');
                nepaliDate.className = 'holiday-nepali-date';
                nepaliDate.textContent = formatNepaliDate(calendarDate);
                dateHeader.append(number, nepaliDate);
                dayCell.appendChild(dateHeader);

                if (isWeekend) {
                    const weekendChip = document.createElement('span');
                    weekendChip.className = 'holiday-chip weekend';
                    weekendChip.textContent = @json(__('Weekend'));
                    dayCell.appendChild(weekendChip);
                }

                dayHolidays.slice(0, 2).forEach((item) => {
                    const chip = document.createElement('span');
                    chip.className = 'holiday-chip';
                    chip.textContent = item.title;
                    dayCell.appendChild(chip);
                });

                if (dayHolidays.length > 2) {
                    const more = document.createElement('span');
                    more.className = 'holiday-chip';
                    more.textContent = `+${dayHolidays.length - 2} ${@json(__('more'))}`;
                    dayCell.appendChild(more);
                }

                dayCell.addEventListener('click', () => {
                    if (!isWeekend && dayHolidays.length === 0) {
                        return;
                    }
                    if (!detailsModal || !detailsBody) {
                        return;
                    }

                    const weekendHtml = isWeekend
                        ? `
                            <div class="border rounded p-2 mb-2">
                                <h6 class="mb-1">{{ __('Weekend') }}</h6>
                                <div class="small text-muted mb-1">${escapeHtml(dateKey)} · ${escapeHtml(formatNepaliDate(calendarDate))}</div>
                                <div>{{ __('This date is a scheduled weekly weekend.') }}</div>
                            </div>
                        `
                        : '';

                    const detailsHtml = dayHolidays.map((item) => `
                        <div class="border rounded p-2 mb-2">
                            <h6 class="mb-1">${escapeHtml(item.title)}</h6>
                            <div class="small text-muted mb-1">${escapeHtml(item.holiday_date)} · ${escapeHtml(formatNepaliDate(calendarDate))} | ${escapeHtml(item.holiday_type)}</div>
                            <div class="small mb-1">Optional: ${item.is_optional ? 'Yes' : 'No'}</div>
                            <div>${item.description ? escapeHtml(item.description) : '-'}</div>
                        </div>
                    `).join('');

                    detailsBody.innerHTML = `${weekendHtml}${detailsHtml}`;
                    detailsModal.show();
                });

                calendarGrid.appendChild(dayCell);
            }
        };

        prevButton.addEventListener('click', () => {
            const nextDate = new Date(currentMonthDate.getFullYear(), currentMonthDate.getMonth() - 1, 1);
            if (nextDate.getFullYear() !== initialYear) {
                return;
            }
            currentMonthDate = nextDate;
            renderCalendar();
        });

        nextButton.addEventListener('click', () => {
            const nextDate = new Date(currentMonthDate.getFullYear(), currentMonthDate.getMonth() + 1, 1);
            if (nextDate.getFullYear() !== initialYear) {
                return;
            }
            currentMonthDate = nextDate;
            renderCalendar();
        });

        renderCalendar();
    })();
</script>
@endpush

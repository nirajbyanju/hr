@extends('layouts.backend')

@php
    // Two presentations of the same data; the tab is remembered in the URL so a
    // refresh or a shared link keeps whichever view the user picked.
    $view = request()->query('view') === 'chart' ? 'chart' : 'structure';

    // Group by manager once, so the recursive node partial never queries again.
    $byId = $employees->keyBy('id');
    $childrenMap = $employees->groupBy('reports_to_id');

    // Roots are the employees with no manager, plus any whose manager is not in
    // this list (soft-deleted or filtered out) so nobody disappears from the chart.
    $roots = $employees->filter(
        fn ($item) => ! $item->reports_to_id || ! $byId->has($item->reports_to_id)
    )->values();

    // Everyone has at most one manager, so the graph is a forest plus — if the
    // data ever loops (A reports to B, B reports to A) — some disjoint cycles.
    // Cycles are unreachable from the roots, so walking from the roots tells us
    // exactly who would otherwise be missing from the chart.
    $reach = function (array $stack) use ($childrenMap, &$seen) {
        while ($stack) {
            $current = array_pop($stack);
            if (isset($seen[$current->id])) {
                continue;
            }
            $seen[$current->id] = true;
            foreach ($childrenMap->get($current->id, collect()) as $child) {
                $stack[] = $child;
            }
        }
    };

    $seen = [];
    $reach($roots->all());

    // Promote one member of each leftover loop to a root and cut the edge that
    // points back into it, so the loop renders as a chain instead of vanishing.
    foreach ($employees as $employee) {
        if (isset($seen[$employee->id])) {
            continue;
        }

        $roots->push($employee);
        $childrenMap->put(
            $employee->reports_to_id,
            $childrenMap->get($employee->reports_to_id, collect())
                ->reject(fn ($item) => $item->id === $employee->id)
                ->values()
        );
        $reach([$employee]);
    }

    $activeCount = $employees->filter(fn ($item) => strtolower((string) $item->employment_status) === 'active')->count();
@endphp

@section('content')
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center">
        <h1><i class="icon-share-alt"></i> {{ $view === 'chart' ? __('Organization Chart') : __('Organization Structure') }}</h1>
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            <div class="org-view-switch mb-3">
                <a href="{{ request()->fullUrlWithQuery(['view' => 'structure']) }}"
                   class="org-view-switch-btn {{ $view === 'structure' ? 'active' : '' }}">
                    <i class="icon-list"></i> {{ __('Organization Structure') }}
                </a>
                <a href="{{ request()->fullUrlWithQuery(['view' => 'chart']) }}"
                   class="org-view-switch-btn {{ $view === 'chart' ? 'active' : '' }}">
                    <i class="icon-share-alt"></i> {{ __('Organization Chart') }}
                </a>
            </div>

            @if($view === 'chart')
                <div class="org-chart-meta mb-3">
                    <span class="org-chart-count"><i class="icon-people"></i> {{ trans_choice('{1} :count Member|[2,*] :count Members', $employees->count(), ['count' => $employees->count()]) }}</span>
                    <span class="org-chart-legend"><i class="org-chart-dot is-active"></i> {{ __('Active') }} ({{ $activeCount }})</span>
                    <span class="org-chart-legend"><i class="org-chart-dot is-inactive"></i> {{ __('Inactive') }} ({{ $employees->count() - $activeCount }})</span>
                    <span class="org-chart-actions">
                        <button type="button" class="btn btn-sm btn-custom-default" id="org_chart_expand_all">{{ __('Expand All') }}</button>
                        <button type="button" class="btn btn-sm btn-custom-default" id="org_chart_collapse_all">{{ __('Collapse All') }}</button>
                    </span>
                </div>

                <div class="content_wrapper content-padded">
                    @if($roots->isEmpty())
                        <div class="alert alert-info mb-0">{{ __('No employee data found.') }}</div>
                    @else
                        <div class="org-chart-scroll">
                            <ul class="org-chart-tree{{ $roots->count() > 1 ? ' has-multiple-roots' : '' }}">
                                @foreach($roots as $root)
                                    @include('hr.employees.partials.org_chart_node', ['node' => $root])
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @else
                @if($authEmployee)
                    <div class="mb-3">
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

                <div>
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
            @endif
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .org-view-switch {
        display: inline-flex;
        gap: 4px;
        padding: 4px;
        background: #f1f3f6;
        border-radius: 10px;
    }
    .org-view-switch-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 16px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 500;
        color: #5b6b7f;
        text-decoration: none;
        transition: background 0.15s ease, color 0.15s ease;
    }
    .org-view-switch-btn:hover {
        color: var(--hr-accent);
    }
    .org-view-switch-btn.active {
        background: #fff;
        color: var(--hr-accent);
        box-shadow: 0 1px 3px rgba(16, 24, 40, 0.12);
    }

    .org-chart-meta {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
        font-size: 13px;
        color: #5b6b7f;
    }
    .org-chart-count {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 7px 14px;
        border: 1px solid #e3e8ef;
        border-radius: 8px;
        background: #fff;
        font-weight: 500;
        color: #25364d;
    }
    .org-chart-legend {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .org-chart-dot,
    .org-chart-status {
        display: inline-block;
        width: 9px;
        height: 9px;
        border-radius: 50%;
    }
    .org-chart-dot.is-active,
    .org-chart-status.is-active {
        background: #12b76a;
    }
    .org-chart-dot.is-inactive,
    .org-chart-status.is-inactive {
        background: #f04438;
    }
    .org-chart-actions {
        margin-left: auto;
        display: inline-flex;
        gap: 8px;
    }

    /* The tree itself: nested <ul>s, connectors drawn with pseudo-element borders
       so no SVG/JS layout pass is needed. */
    .org-chart-scroll {
        overflow-x: auto;
        overflow-y: hidden;
        padding: 24px 8px 8px;
    }
    .org-chart-tree,
    .org-chart-children {
        display: flex;
        justify-content: center;
        list-style: none;
        margin: 0;
        padding: 0;
    }
    .org-chart-children {
        padding-top: 34px;
        position: relative;
    }
    .org-chart-node {
        position: relative;
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 0 12px;
    }

    /* vertical stub dropping out of a parent into its children row */
    .org-chart-children::before {
        content: '';
        position: absolute;
        top: 0;
        left: 50%;
        width: 2px;
        height: 34px;
        background: #dfe4ec;
        transform: translateX(-50%);
    }
    /* horizontal rail + per-child vertical stub */
    .org-chart-node::before,
    .org-chart-node::after {
        content: '';
        position: absolute;
        background: #dfe4ec;
    }
    .org-chart-children > .org-chart-node::before {
        top: -34px;
        left: 0;
        right: 0;
        height: 2px;
    }
    .org-chart-children > .org-chart-node::after {
        top: -34px;
        left: 50%;
        width: 2px;
        height: 34px;
        transform: translateX(-50%);
    }
    /* trim the rail so it stops at the first and last child instead of
       overhanging into empty space */
    .org-chart-children > .org-chart-node:first-child::before {
        left: 50%;
    }
    .org-chart-children > .org-chart-node:last-child::before {
        right: 50%;
    }
    .org-chart-children > .org-chart-node:only-child::before {
        display: none;
    }
    /* multiple roots get the same rail treatment so they read as one org */
    .org-chart-tree.has-multiple-roots > .org-chart-node::before {
        top: -18px;
        left: 0;
        right: 0;
        height: 2px;
    }
    .org-chart-tree.has-multiple-roots > .org-chart-node::after {
        top: -18px;
        left: 50%;
        width: 2px;
        height: 18px;
        transform: translateX(-50%);
    }
    .org-chart-tree.has-multiple-roots > .org-chart-node:first-child::before {
        left: 50%;
    }
    .org-chart-tree.has-multiple-roots > .org-chart-node:last-child::before {
        right: 50%;
    }

    .org-chart-card {
        position: relative;
        width: 158px;
        border: 1px solid #e3e8ef;
        border-radius: 10px;
        background: #fff;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }
    .org-chart-card:hover {
        border-color: var(--hr-accent);
        box-shadow: 0 4px 14px rgba(16, 24, 40, 0.1);
    }
    .org-chart-card.is-self {
        border-color: var(--hr-accent);
        box-shadow: 0 0 0 3px rgba(var(--hr-accent-rgb), 0.15);
    }
    .org-chart-card-link {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 14px 10px 12px;
        text-decoration: none;
        color: inherit;
    }
    .org-chart-avatar {
        position: relative;
        display: block;
        width: 44px;
        height: 44px;
        margin-bottom: 9px;
    }
    .org-chart-avatar img {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        object-fit: cover;
        background: #eef1f5;
    }
    .org-chart-avatar .org-chart-status {
        position: absolute;
        right: 0;
        bottom: 1px;
        width: 12px;
        height: 12px;
        border: 2px solid #fff;
    }
    .org-chart-name {
        font-size: 12.5px;
        font-weight: 600;
        color: #25364d;
        line-height: 1.25;
        text-align: center;
        word-break: break-word;
    }
    .org-chart-role {
        margin-top: 3px;
        font-size: 11.5px;
        color: var(--hr-accent);
        text-align: center;
        line-height: 1.25;
    }
    .org-chart-code {
        margin-top: 2px;
        font-size: 10.5px;
        color: #98a2b3;
    }

    .org-chart-toggle {
        position: absolute;
        left: 50%;
        bottom: -13px;
        transform: translateX(-50%);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 26px;
        height: 26px;
        padding: 0;
        border: 2px solid #fff;
        border-radius: 50%;
        background: #12b76a;
        color: #fff;
        font-size: 11px;
        line-height: 1;
        cursor: pointer;
        z-index: 2;
    }
    .org-chart-toggle i {
        transition: transform 0.18s ease;
    }
    .org-chart-toggle-count {
        display: none;
        font-size: 11px;
        font-weight: 600;
    }
    /* collapsed: hide the subtree, flip the chevron, show how many are hidden */
    .org-chart-node.is-collapsed > .org-chart-children {
        display: none;
    }
    .org-chart-node.is-collapsed > .org-chart-card .org-chart-toggle {
        background: var(--hr-accent);
    }
    .org-chart-node.is-collapsed > .org-chart-card .org-chart-toggle i {
        display: none;
    }
    .org-chart-node.is-collapsed > .org-chart-card .org-chart-toggle-count {
        display: inline;
    }
</style>
@endpush

@push('scripts')
<script>
    (function () {
        const tree = document.querySelector('.org-chart-tree');
        if (!tree) {
            return;
        }

        function setCollapsed(node, collapsed) {
            node.classList.toggle('is-collapsed', collapsed);
            const toggle = node.querySelector(':scope > .org-chart-card > .org-chart-toggle');
            if (toggle) {
                toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            }
        }

        tree.addEventListener('click', function (event) {
            const toggle = event.target.closest('.org-chart-toggle');
            if (!toggle) {
                return;
            }

            event.preventDefault();
            const node = toggle.closest('.org-chart-node');
            setCollapsed(node, !node.classList.contains('is-collapsed'));
        });

        function setAll(collapsed) {
            tree.querySelectorAll('.org-chart-node').forEach(function (node) {
                if (node.querySelector(':scope > .org-chart-children')) {
                    setCollapsed(node, collapsed);
                }
            });
        }

        const expandBtn = document.getElementById('org_chart_expand_all');
        const collapseBtn = document.getElementById('org_chart_collapse_all');
        if (expandBtn) {
            expandBtn.addEventListener('click', function () { setAll(false); });
        }
        if (collapseBtn) {
            collapseBtn.addEventListener('click', function () { setAll(true); });
        }

        // Centre the horizontal scroll on load; wide orgs otherwise open pinned
        // to the far left with the root off-screen.
        const scroller = document.querySelector('.org-chart-scroll');
        if (scroller) {
            scroller.scrollLeft = (scroller.scrollWidth - scroller.clientWidth) / 2;
        }
    })();
</script>
@endpush

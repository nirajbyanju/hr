@php
    /** @var \App\Models\Employee $node */
    $children = $childrenMap[$node->id] ?? collect();
    $isActive = strtolower((string) $node->employment_status) === 'active';
    $avatar = $node->avatar_path
        ? asset($node->avatar_path)
        : asset(\App\Support\DefaultAvatar::forGender($node->gender));
    $isSelf = isset($authEmployee) && $authEmployee && (int) $authEmployee->id === (int) $node->id;
@endphp
<li class="org-chart-node">
    <div class="org-chart-card{{ $isSelf ? ' is-self' : '' }}{{ $children->isNotEmpty() ? ' has-children' : '' }}">
        <a href="{{ route('employees.show', $node) }}" class="org-chart-card-link">
            <span class="org-chart-avatar">
                <img src="{{ $avatar }}" alt="{{ trim($node->first_name.' '.$node->last_name) }}">
                <span class="org-chart-status {{ $isActive ? 'is-active' : 'is-inactive' }}"
                      title="{{ __(ucfirst(str_replace('_', ' ', (string) $node->employment_status))) }}"></span>
            </span>
            <span class="org-chart-name">{{ trim($node->first_name.' '.$node->last_name) }}</span>
            <span class="org-chart-role">{{ $node->designation?->name ?? __('Unassigned') }}</span>
            <span class="org-chart-code">{{ $node->employee_code }}</span>
        </a>

        @if($children->isNotEmpty())
            <button type="button"
                    class="org-chart-toggle"
                    aria-expanded="true"
                    title="{{ __('Collapse / Expand') }}">
                <i class="icon-arrow-up"></i>
                <span class="org-chart-toggle-count">{{ $children->count() }}</span>
            </button>
        @endif
    </div>

    @if($children->isNotEmpty())
        <ul class="org-chart-children">
            @foreach($children as $child)
                @include('hr.employees.partials.org_chart_node', ['node' => $child])
            @endforeach
        </ul>
    @endif
</li>

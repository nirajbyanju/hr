@extends('layouts.backend')

@section('content')
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center">
        <h1><i class="icon-bell"></i> {{ __('Notices & Announcements') }}</h1>
        @if($canCreate)
            <a href="{{ route('announcements.create') }}" class="btn btn-custom"><i class="icon-plus"></i> {{ __('Add Notice/Announcement') }}</a>
        @endif
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            <div class="card no-border">
                <div class="content_wrapper content-padded">
                    <div class="notice-page-meta d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <div class="notice-meta-chip">
                            <i class="icon-list"></i>
                            <span>Total: {{ $announcements->total() }}</span>
                        </div>
                        <div class="notice-meta-chip">
                            <i class="icon-clock"></i>
                            <span>{{ __('Ordered latest first') }}</span>
                        </div>
                    </div>

                    <form method="GET" class="row g-2 mb-3">
                        <div class="col-md-4">
                            <input type="text" name="q" value="{{ $filters['q'] }}" class="form-control" placeholder="{{ __('Search title or content') }}">
                        </div>
                        <div class="col-md-2">
                            <select name="type" class="form-control">
                                <option value="">{{ __('All Types') }}</option>
                                <option value="notice" {{ $filters['type'] === 'notice' ? 'selected' : '' }}>{{ __('Notice') }}</option>
                                <option value="announcement" {{ $filters['type'] === 'announcement' ? 'selected' : '' }}>{{ __('Announcement') }}</option>
                            </select>
                        </div>
                        @if($canManageStatuses)
                            <div class="col-md-2">
                                <select name="status" class="form-control">
                                    <option value="">{{ __('All Status') }}</option>
                                    <option value="pending" {{ $filters['status'] === 'pending' ? 'selected' : '' }}>{{ __('Pending') }}</option>
                                    <option value="approved" {{ $filters['status'] === 'approved' ? 'selected' : '' }}>{{ __('Approved') }}</option>
                                    <option value="rejected" {{ $filters['status'] === 'rejected' ? 'selected' : '' }}>{{ __('Rejected') }}</option>
                                    <option value="published" {{ $filters['status'] === 'published' ? 'selected' : '' }}>{{ __('Published') }}</option>
                                    <option value="expired" {{ $filters['status'] === 'expired' ? 'selected' : '' }}>{{ __('Expired') }}</option>
                                </select>
                            </div>
                        @endif
                        <div class="col-md-2">
                            <select name="per_page" class="form-control">
                                @foreach([10,20,50,100] as $size)
                                    <option value="{{ $size }}" {{ (int) $filters['per_page'] === $size ? 'selected' : '' }}>{{ $size }} / page</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-{{ $canManageStatuses ? '2' : '4' }} d-flex gap-2">
                            <button class="btn btn-custom" type="submit"><i class="icon-magnifier"></i> {{ __('Filter') }}</button>
                            <a href="{{ route('announcements.index') }}" class="btn btn-custom-default"><i class="icon-refresh"></i> {{ __('Reset') }}</a>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle notice-table">
                            <thead>
                                <tr>
                                    <th><i class="icon-doc me-1"></i> {{ __('Title') }}</th>
                                    <th><i class="icon-tag me-1"></i> {{ __('Type') }}</th>
                                    <th><i class="icon-flag me-1"></i> {{ __('Priority') }}</th>
                                    <th><i class="icon-layers me-1"></i> {{ __('Status') }}</th>
                                    <th><i class="icon-user me-1"></i> {{ __('Created By') }}</th>
                                    <th><i class="icon-clock me-1"></i> {{ __('Published At') }}</th>
                                    <th><i class="icon-settings me-1"></i> {{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($announcements as $announcement)
                                    @php($status = $announcement->workflowStatus())
                                    <tr>
                                        <td>
                                            <strong>{{ $announcement->title }}</strong>
                                        </td>
                                        <td>
                                            <span class="badge {{ $announcement->announcement_type === 'notice' ? 'bg-info' : 'bg-primary' }}">
                                                <i class="{{ $announcement->announcement_type === 'notice' ? 'icon-bell' : 'icon-doc' }}"></i>
                                                {{ __(ucfirst($announcement->announcement_type)) }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge {{ $announcement->priority === 'high' ? 'bg-danger' : 'bg-secondary' }}">
                                                {{ __(ucfirst($announcement->priority)) }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($status === 'published')
                                                <span class="badge bg-success">{{ __('Published') }}</span>
                                            @elseif($status === 'approved')
                                                <span class="badge bg-info">{{ __('Approved') }}</span>
                                            @elseif($status === 'rejected')
                                                <span class="badge bg-danger">{{ __('Rejected') }}</span>
                                            @else
                                                <span class="badge bg-warning text-dark">{{ __('Pending') }}</span>
                                            @endif
                                            @if($announcement->isExpired())
                                                <span class="badge bg-dark">{{ __('Expired') }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $announcement->creator?->name ?? __('System') }}</td>
                                        <td>{{ $announcement->publish_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                        <td class="action-buttons">
                                            <a href="{{ route('announcements.show', $announcement) }}" title="{{ __('Details') }}"><i class="icon-eye"></i></a>

                                            @if($canApprove && $announcement->approval_status !== 'approved')
                                                <form method="POST" action="{{ route('announcements.approve', $announcement) }}" class="d-inline" onsubmit="return confirm('Approve this item?');">
                                                    @csrf
                                                    <button type="submit" title="{{ __('Approve') }}"><i class="icon-check"></i></button>
                                                </form>
                                            @endif

                                            @if($canPublish && $announcement->publish_at === null)
                                                <form method="POST" action="{{ route('announcements.publish', $announcement) }}" class="d-inline" onsubmit="return confirm('Publish this item?');">
                                                    @csrf
                                                    <button type="submit" title="{{ __('Publish') }}"><i class="icon-bell"></i></button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center">{{ __('No notices/announcements found.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    {{ $announcements->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

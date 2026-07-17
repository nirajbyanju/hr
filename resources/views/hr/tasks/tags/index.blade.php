@extends('layouts.backend')
@section('title', 'Task Tags')

@section('content')
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center">
        <h1><i class="icon-tag"></i> {{ __('Task Tags') }}</h1>
        <a href="{{ route('task-tags.create') }}" class="btn btn-custom"><i class="icon-plus"></i> {{ __('Add Tag') }}</a>
    </div>

    @include('partials.flash')

    <div class="page-content"><div class="container-fluid"><div class="card no-border"><div class="content_wrapper content-padded">
        <div class="table-responsive"><table class="table table-bordered align-middle">
            <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Color') }}</th><th>{{ __('Active') }}</th><th>{{ __('Actions') }}</th></tr></thead>
            <tbody>
                @forelse($tags as $tag)
                    <tr>
                        <td>{{ $tag->name }}</td>
                        <td><span class="badge" style="background-color: {{ $tag->color }}">{{ $tag->color }}</span></td>
                        <td>{{ $tag->is_active ? __('Yes') : __('No') }}</td>
                        <td class="action-buttons">
                            <a href="{{ route('task-tags.edit', $tag) }}"><i class="icon-pencil"></i></a>
                            <form method="POST" action="{{ route('task-tags.destroy', $tag) }}" onsubmit="return confirm('{{ __('Delete this tag?') }}');" class="d-inline">@csrf @method('DELETE')<button type="submit"><i class="icon-trash"></i></button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center">{{ __('No tags yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table></div>
        {{ $tags->links('pagination::bootstrap-5') }}
    </div></div></div></div>
</div>
@endsection

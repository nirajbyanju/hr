@extends('layouts.backend')
@section('title', 'Task Categories')

@section('content')
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center">
        <h1><i class="icon-folder"></i> {{ __('Task Categories') }}</h1>
        <a href="{{ route('task-categories.create') }}" class="btn btn-custom"><i class="icon-plus"></i> {{ __('Add Category') }}</a>
    </div>

    @include('partials.flash')

    <div class="page-content"><div class="container-fluid"><div><div class="content_wrapper content-padded">
        <div class="table-responsive"><table class="table table-bordered align-middle">
            <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Color') }}</th><th>{{ __('Active') }}</th><th>{{ __('Actions') }}</th></tr></thead>
            <tbody>
                @forelse($categories as $category)
                    <tr>
                        <td>{{ $category->name }}</td>
                        <td><span class="badge" style="background-color: {{ $category->color }}">{{ $category->color }}</span></td>
                        <td>{{ $category->is_active ? __('Yes') : __('No') }}</td>
                        <td class="action-buttons">
                            <a href="{{ route('task-categories.edit', $category) }}"><i class="icon-pencil"></i></a>
                            <form method="POST" action="{{ route('task-categories.destroy', $category) }}" onsubmit="return confirm('{{ __('Delete this category?') }}');" class="d-inline">@csrf @method('DELETE')<button type="submit"><i class="icon-trash"></i></button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center">{{ __('No categories yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table></div>
        {{ $categories->links('pagination::bootstrap-5') }}
    </div></div></div></div>
</div>
@endsection

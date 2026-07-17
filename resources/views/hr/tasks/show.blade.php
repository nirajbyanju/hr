@extends('layouts.backend')
@section('title', 'Task Details')

@php
    $actionLabels = [
        'accept' => __('Accept'),
        'reject' => __('Reject'),
        'start' => __('Start'),
        'hold' => __('Put On Hold'),
        'resume' => __('Resume'),
        'submit_review' => __('Submit for Review'),
        'review_approve' => __('Approve Review'),
        'review_reject' => __('Request Changes'),
        'complete' => __('Mark Completed'),
        'close' => __('Close'),
        'reopen' => __('Reopen'),
    ];
    $user = auth()->user();
@endphp

@section('content')
<div class="wrapper-page">
    <div class="page-title d-flex justify-content-between align-items-center">
        <h1><i class="icon-list"></i> #{{ $task->id }} {{ $task->title }}</h1>
        <div class="d-flex gap-2">
            @if($user?->hasPermission('task.watch'))
                @if($isWatching)
                    <form method="POST" action="{{ route('tasks.unwatch', $task) }}">@csrf @method('DELETE')<button type="submit" class="btn btn-custom-default"><i class="icon-eye"></i> {{ __('Watching') }}</button></form>
                @else
                    <form method="POST" action="{{ route('tasks.watch', $task) }}">@csrf<button type="submit" class="btn btn-custom-default"><i class="icon-eye"></i> {{ __('Watch') }}</button></form>
                @endif
            @endif
            @if($user?->hasPermission('task.update'))<a href="{{ route('tasks.edit', $task) }}" class="btn btn-custom">{{ __('Edit') }}</a>@endif
            <a href="{{ route('tasks.index') }}" class="btn btn-custom-default"><i class="icon-arrow-left"></i> {{ __('Back') }}</a>
        </div>
    </div>

    @include('partials.flash')

    <div class="page-content"><div class="container-fluid"><div class="row g-3">
        <div class="col-md-7">
            <div><div class="content_wrapper content-padded">
                <h5 class="table_banner_title mb-3">{{ __('Task Summary') }}</h5>
                <div class="row">
                    <div class="col-md-6"><p><strong>{{ __('Project:') }}</strong> {{ $task->project?->name ?? '-' }}</p></div>
                    <div class="col-md-6"><p><strong>{{ __('Category:') }}</strong> {{ $task->category?->name ?? '-' }}</p></div>
                    <div class="col-md-6"><p><strong>{{ __('Priority:') }}</strong> @if($task->priority)<span class="badge" style="background-color: {{ $task->priority->color }}">{{ $task->priority->name }}</span>@endif</p></div>
                    <div class="col-md-6"><p><strong>{{ __('Status:') }}</strong> @if($task->status)<span class="badge" style="background-color: {{ $task->status->color }}">{{ $task->status->name }}</span>@endif</p></div>
                    <div class="col-md-6"><p><strong>{{ __('Owner:') }}</strong> {{ trim(($task->owner?->first_name ?? '').' '.($task->owner?->last_name ?? '')) ?: '-' }}</p></div>
                    <div class="col-md-6"><p><strong>{{ __('Created By:') }}</strong> {{ trim(($task->creator?->first_name ?? '').' '.($task->creator?->last_name ?? '')) ?: '-' }}</p></div>
                    <div class="col-md-6"><p><strong>{{ __('Dates:') }}</strong> {{ $task->start_date ?? '-' }} &rarr; {{ $task->due_date ?? '-' }}</p></div>
                    <div class="col-md-6"><p><strong>{{ __('Hours:') }}</strong> {{ __('Est.') }} {{ $task->estimated_hours ?? '-' }} / {{ __('Actual') }} {{ $task->actual_hours ?? '-' }}</p></div>
                    @if($task->parentTask)<div class="col-md-6"><p><strong>{{ __('Parent Task:') }}</strong> <a href="{{ route('tasks.show', $task->parentTask) }}">#{{ $task->parentTask->id }} {{ $task->parentTask->title }}</a></p></div>@endif
                    <div class="col-md-6"><p><strong>{{ __('Visibility:') }}</strong> {{ ucfirst($task->visibility) }}</p></div>
                </div>
                <p><strong>{{ __('Progress:') }}</strong></p>
                <div class="progress mb-2" style="height: 10px;"><div class="progress-bar" role="progressbar" style="width: {{ (int)$task->progress_percent }}%"></div></div>
                <p class="small text-muted">{{ (int)$task->progress_percent }}%</p>
                @if($task->tags->isNotEmpty())
                    <p><strong>{{ __('Tags:') }}</strong> @foreach($task->tags as $tag)<span class="badge" style="background-color: {{ $tag->color }}">{{ $tag->name }}</span>@endforeach</p>
                @endif
                <p><strong>{{ __('Description:') }}</strong></p>
                <div>{!! nl2br(e($task->description ?: '-')) !!}</div>

                @if($task->childTasks->isNotEmpty())
                    <hr>
                    <h6>{{ __('Sub-tasks') }}</h6>
                    <ul>@foreach($task->childTasks as $child)<li><a href="{{ route('tasks.show', $child) }}">#{{ $child->id }} {{ $child->title }}</a> @if($child->status)<span class="badge" style="background-color: {{ $child->status->color }}">{{ $child->status->name }}</span>@endif</li>@endforeach</ul>
                @endif
            </div></div>

            <div class="mt-3"><div class="content_wrapper content-padded">
                <h5 class="table_banner_title mb-3">{{ __('Assignments') }}</h5>
                @forelse($task->assignments as $assignment)
                    @php($isMine = $myAssignment && $myAssignment->id === $assignment->id)
                    @php($isAdminTier = $user?->hasAnyPermission(['task.delete', 'task.assign', 'task.assign-team']))
                    <div class="border p-2 mb-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>{{ trim(($assignment->employee?->first_name ?? '').' '.($assignment->employee?->last_name ?? '')) }}</strong>
                                @if($assignment->is_owner)<span class="badge bg-dark">{{ __('Owner') }}</span>@endif
                                @if($assignment->status)<span class="badge" style="background-color: {{ $assignment->status->color }}">{{ $assignment->status->name }}</span>@endif
                                <span class="small text-muted">{{ (int)$assignment->progress_percent }}%</span>
                            </div>
                            @if($isAdminTier && !$isMine)
                                <form method="POST" action="{{ route('tasks.assignments.destroy', $assignment) }}" onsubmit="return confirm('{{ __('Remove this assignment?') }}');">@csrf @method('DELETE')<button type="submit" class="btn btn-sm btn-custom-default"><i class="icon-close"></i></button></form>
                            @endif
                        </div>

                        @if($isMine && !empty($myAvailableActions))
                            <form method="POST" action="{{ route('tasks.assignments.transition', $assignment) }}" class="d-flex flex-wrap gap-1 mt-2">
                                @csrf @method('PATCH')
                                <select name="action" class="form-control form-control-sm" style="max-width: 200px;" required>
                                    @foreach($myAvailableActions as $action)<option value="{{ $action }}">{{ $actionLabels[$action] ?? $action }}</option>@endforeach
                                </select>
                                <input type="text" name="reason" class="form-control form-control-sm" style="max-width: 220px;" placeholder="{{ __('Reason (if required)') }}">
                                <button type="submit" class="btn btn-sm btn-custom">{{ __('Apply') }}</button>
                            </form>
                            @if($user?->hasPermission('task.transfer-request'))
                                <form method="POST" action="{{ route('tasks.transfers.store') }}" class="d-flex flex-wrap gap-1 mt-2">
                                    @csrf
                                    <input type="hidden" name="task_assignment_id" value="{{ $assignment->id }}">
                                    <select name="to_employee_id" class="form-control form-control-sm js-example-basic-single" style="max-width: 200px;" required>
                                        <option value="">{{ __('Transfer to...') }}</option>
                                        @foreach($employees as $employee)
                                            @if($employee->id !== $assignment->employee_id)
                                                <option value="{{ $employee->id }}">{{ trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')) }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                    <input type="text" name="reason" class="form-control form-control-sm" style="max-width: 220px;" placeholder="{{ __('Reason') }}" required>
                                    <button type="submit" class="btn btn-sm btn-custom-default">{{ __('Request Transfer') }}</button>
                                </form>
                            @endif
                        @elseif($isAdminTier)
                            <form method="POST" action="{{ route('tasks.assignments.transition', $assignment) }}" class="d-flex flex-wrap gap-1 mt-2">
                                @csrf @method('PATCH')
                                <select name="action" class="form-control form-control-sm" style="max-width: 200px;" required>
                                    @foreach($actionLabels as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                                </select>
                                <input type="text" name="reason" class="form-control form-control-sm" style="max-width: 220px;" placeholder="{{ __('Reason (if required)') }}">
                                <button type="submit" class="btn btn-sm btn-custom">{{ __('Apply') }}</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="text-muted">{{ __('No one is assigned yet.') }}</p>
                @endforelse

                @if($user?->hasAnyPermission(['task.assign', 'task.assign-team']))
                    <hr>
                    <h6>{{ __('Assign Team Members') }}</h6>
                    <form method="POST" action="{{ route('tasks.assignments.store', $task) }}" class="row g-2">
                        @csrf
                        <div class="col-md-6"><select name="employee_ids[]" class="form-control js-example-basic-multiple" multiple required>@foreach($employees as $employee)<option value="{{ $employee->id }}">{{ trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')) }} ({{ $employee->employee_code }})</option>@endforeach</select></div>
                        <div class="col-md-4"><select name="owner_employee_id" class="form-control js-example-basic-single"><option value="">{{ __('Owner (optional)') }}</option>@foreach($employees as $employee)<option value="{{ $employee->id }}">{{ trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')) }}</option>@endforeach</select></div>
                        <div class="col-md-2"><button type="submit" class="btn btn-custom w-100">{{ __('Assign') }}</button></div>
                    </form>
                @endif
            </div></div>

            <div class="mt-3"><div class="content_wrapper content-padded">
                <h5 class="table_banner_title mb-3">{{ __('Checklist') }}</h5>
                @forelse($task->checklists as $checklist)
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <strong>{{ $checklist->title }}</strong>
                            @if($user?->hasPermission('task_checklist.manage'))
                                <form method="POST" action="{{ route('tasks.checklists.destroy', $checklist) }}" onsubmit="return confirm('{{ __('Delete this checklist?') }}');">@csrf @method('DELETE')<button type="submit" class="btn btn-sm btn-custom-default"><i class="icon-trash"></i></button></form>
                            @endif
                        </div>
                        @foreach($checklist->items as $item)
                            <div class="d-flex align-items-center gap-2">
                                <form method="POST" action="{{ route('tasks.checklist-items.toggle', $item) }}">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="btn btn-sm p-0 border-0 bg-transparent" title="{{ __('Toggle') }}" {{ $user?->hasAnyPermission(['task_checklist.check','task_checklist.manage']) ? '' : 'disabled' }}>
                                        <i class="icon-{{ $item->is_checked ? 'check' : 'square' }}"></i>
                                    </button>
                                </form>
                                <span class="{{ $item->is_checked ? 'text-decoration-line-through text-muted' : '' }}">{{ $item->title }}</span>
                                @if($user?->hasPermission('task_checklist.manage'))
                                    <form method="POST" action="{{ route('tasks.checklist-items.destroy', $item) }}" class="ms-auto" onsubmit="return confirm('{{ __('Remove this item?') }}');">@csrf @method('DELETE')<button type="submit" class="btn btn-sm p-0 border-0 bg-transparent"><i class="icon-close"></i></button></form>
                                @endif
                            </div>
                        @endforeach
                        @if($user?->hasPermission('task_checklist.manage'))
                            <form method="POST" action="{{ route('tasks.checklists.items.store', $checklist) }}" class="d-flex gap-2 mt-1">
                                @csrf
                                <input type="text" name="title" class="form-control form-control-sm" placeholder="{{ __('New item') }}" required>
                                <button type="submit" class="btn btn-sm btn-custom-default">{{ __('Add') }}</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="text-muted">{{ __('No checklists yet.') }}</p>
                @endforelse

                @if($user?->hasPermission('task_checklist.manage'))
                    <form method="POST" action="{{ route('tasks.checklists.store', $task) }}" class="d-flex gap-2 mt-2">
                        @csrf
                        <input type="text" name="title" class="form-control form-control-sm" placeholder="{{ __('New checklist title') }}" required>
                        <button type="submit" class="btn btn-sm btn-custom">{{ __('Add Checklist') }}</button>
                    </form>
                @endif
            </div></div>

            <div class="mt-3"><div class="content_wrapper content-padded">
                <h5 class="table_banner_title mb-3">{{ __('Dependencies') }}</h5>
                <p><strong>{{ __('Depends on:') }}</strong></p>
                <ul>
                    @forelse($task->dependencies as $dependency)
                        <li>
                            <a href="{{ route('tasks.show', $dependency->depends_on_task_id) }}">#{{ $dependency->dependsOnTask?->id }} {{ $dependency->dependsOnTask?->title }}</a>
                            @if($dependency->dependsOnTask?->status)<span class="badge" style="background-color: {{ $dependency->dependsOnTask->status->color }}">{{ $dependency->dependsOnTask->status->name }}</span>@endif
                            @if($user?->hasPermission('task.update'))<form method="POST" action="{{ route('tasks.dependencies.destroy', $dependency) }}" class="d-inline" onsubmit="return confirm('{{ __('Remove dependency?') }}');">@csrf @method('DELETE')<button type="submit" class="btn btn-sm p-0 border-0 bg-transparent"><i class="icon-close"></i></button></form>@endif
                        </li>
                    @empty
                        <li class="text-muted">{{ __('None') }}</li>
                    @endforelse
                </ul>
                <p><strong>{{ __('Blocks:') }}</strong></p>
                <ul>
                    @forelse($task->dependents as $dependent)
                        <li><a href="{{ route('tasks.show', $dependent->task_id) }}">#{{ $dependent->task?->id }} {{ $dependent->task?->title }}</a></li>
                    @empty
                        <li class="text-muted">{{ __('None') }}</li>
                    @endforelse
                </ul>
                @if($user?->hasPermission('task.update'))
                    <form method="POST" action="{{ route('tasks.dependencies.store', $task) }}" class="d-flex gap-2">
                        @csrf
                        <select name="depends_on_task_id" class="form-control form-control-sm js-example-basic-single" required>
                            <option value="">{{ __('Select task this depends on') }}</option>
                            @foreach($taskOptions as $option)<option value="{{ $option->id }}">#{{ $option->id }} {{ $option->title }}</option>@endforeach
                        </select>
                        <button type="submit" class="btn btn-sm btn-custom-default">{{ __('Add') }}</button>
                    </form>
                @endif
            </div></div>
        </div>

        <div class="col-md-5">
            <div><div class="content_wrapper content-padded">
                <h5 class="table_banner_title mb-3">{{ __('Attachments') }}</h5>
                @forelse($task->attachments as $attachment)
                    <div class="d-flex justify-content-between align-items-center border-bottom py-1">
                        <div>
                            @if($attachment->isPreviewable())
                                <a href="{{ route('tasks.attachments.preview', $attachment) }}" target="_blank">{{ $attachment->title }}</a>
                            @else
                                <a href="{{ route('tasks.attachments.download', $attachment) }}">{{ $attachment->title }}</a>
                            @endif
                            <span class="small text-muted">({{ $attachment->humanFileSize() }})</span>
                        </div>
                        <div class="d-flex gap-1">
                            <a href="{{ route('tasks.attachments.download', $attachment) }}" title="{{ __('Download') }}"><i class="icon-cloud-download"></i></a>
                            @if($user?->hasPermission('task_attachment.delete'))
                                <form method="POST" action="{{ route('tasks.attachments.destroy', $attachment) }}" onsubmit="return confirm('{{ __('Remove this attachment?') }}');">@csrf @method('DELETE')<button type="submit" class="btn btn-sm p-0 border-0 bg-transparent"><i class="icon-trash"></i></button></form>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-muted">{{ __('No attachments yet.') }}</p>
                @endforelse

                @if($user?->hasPermission('task_attachment.upload'))
                    <form method="POST" action="{{ route('tasks.attachments.store', $task) }}" enctype="multipart/form-data" class="d-flex gap-2 mt-2">
                        @csrf
                        <input type="file" name="file" class="form-control form-control-sm" required>
                        <button type="submit" class="btn btn-sm btn-custom">{{ __('Upload') }}</button>
                    </form>
                    <p class="small text-muted mt-1">{{ __('PDF, Word, Excel, images, ZIP, video - up to 50MB.') }}</p>
                @endif
            </div></div>

            <div class="mt-3"><div class="content_wrapper content-padded">
                <h5 class="table_banner_title mb-3">{{ __('Comments') }}</h5>
                <div class="scroll-panel-sm">
                    @forelse($task->comments as $comment)
                        @php($name = trim(($comment->employee?->first_name ?? '').' '.($comment->employee?->last_name ?? '')))
                        @php($isOwnComment = $user?->employee && $user->employee->id === $comment->employee_id)
                        <div class="border p-2 mb-2">
                            <div class="small text-muted d-flex justify-content-between">
                                <span>{{ $name !== '' ? $name : 'User' }} - {{ $comment->created_at?->format('Y-m-d H:i') }} @if($comment->edited_at)({{ __('edited') }})@endif</span>
                                @if($isOwnComment || $user?->hasAnyPermission(['task.delete','task.assign-team']))
                                    <form method="POST" action="{{ route('tasks.comments.destroy', $comment) }}" onsubmit="return confirm('{{ __('Delete this comment?') }}');">@csrf @method('DELETE')<button type="submit" class="btn btn-sm p-0 border-0 bg-transparent"><i class="icon-trash"></i></button></form>
                                @endif
                            </div>
                            <div>{{ $comment->comment }}</div>
                            @if($comment->mentions->isNotEmpty())
                                <div class="small">{{ __('Mentioned:') }} @foreach($comment->mentions as $mention)<span class="badge bg-info">{{ '@' . trim(($mention->employee?->first_name ?? '')) }}</span>@endforeach</div>
                            @endif
                            @if($comment->attachments->isNotEmpty())
                                <div class="small">@foreach($comment->attachments as $att)<a href="{{ route('tasks.attachments.download', $att) }}">{{ $att->title }}</a> @endforeach</div>
                            @endif
                            @foreach($comment->replies as $reply)
                                @php($replyName = trim(($reply->employee?->first_name ?? '').' '.($reply->employee?->last_name ?? '')))
                                <div class="border-start ps-2 mt-2 ms-2">
                                    <div class="small text-muted">{{ $replyName !== '' ? $replyName : 'User' }} - {{ $reply->created_at?->format('Y-m-d H:i') }}</div>
                                    <div>{{ $reply->comment }}</div>
                                </div>
                            @endforeach
                            @if($user?->hasPermission('task_comment.create'))
                                <a class="small" data-bs-toggle="collapse" href="#reply-{{ $comment->id }}">{{ __('Reply') }}</a>
                                <div class="collapse" id="reply-{{ $comment->id }}">
                                    <form method="POST" action="{{ route('tasks.comments.store', $task) }}" class="mt-1">
                                        @csrf
                                        <input type="hidden" name="parent_comment_id" value="{{ $comment->id }}">
                                        <textarea name="comment" class="form-control form-control-sm" rows="2" required></textarea>
                                        <button type="submit" class="btn btn-sm btn-custom-default mt-1">{{ __('Reply') }}</button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="text-muted">{{ __('No comments yet.') }}</div>
                    @endforelse
                </div>

                @if($user?->hasPermission('task_comment.create'))
                    <hr>
                    <form method="POST" action="{{ route('tasks.comments.store', $task) }}">
                        @csrf
                        <textarea name="comment" class="form-control" rows="3" placeholder="{{ __('Write comment') }}" required></textarea>
                        <select name="mention_employee_ids[]" class="form-control js-example-basic-multiple mt-2" multiple>
                            @foreach($employees as $employee)<option value="{{ $employee->id }}">{{ '@' . trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')) }}</option>@endforeach
                        </select>
                        <button type="submit" class="btn btn-custom mt-2">{{ __('Add Comment') }}</button>
                    </form>
                @endif
            </div></div>

            <div class="mt-3"><div class="content_wrapper content-padded">
                <h5 class="table_banner_title mb-3">{{ __('Activity Timeline') }}</h5>
                <div class="scroll-panel-sm">
                    @forelse($task->activityLogs as $log)
                        <div class="mb-2">
                            <div class="small text-muted">{{ $log->occurred_at?->format('Y-m-d H:i') }} - {{ $log->causer?->name ?? __('System') }}</div>
                            <div>{{ $log->description }}</div>
                        </div>
                    @empty
                        <div class="text-muted">{{ __('No activity recorded yet.') }}</div>
                    @endforelse
                </div>
            </div></div>
        </div>
    </div></div></div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    if ($.fn.select2) {
        $('.js-example-basic-single, .js-example-basic-multiple').select2();
    }
})();
</script>
@endpush

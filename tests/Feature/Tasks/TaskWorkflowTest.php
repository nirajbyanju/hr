<?php

namespace Tests\Feature\Tasks;

use App\Models\TaskAssignment;
use App\Models\TaskDependency;
use App\Modules\Tasks\Services\TaskAssignmentService;
use App\Modules\Tasks\Services\TaskTransferService;
use App\Modules\Tasks\Services\TaskWorkflowService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TaskWorkflowTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTaskFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_assignee_can_accept_and_start_their_own_assignment(): void
    {
        $assignee = $this->makeUserWithPermissions($this->selfServiceSlugs(), 'employee');
        $admin = $this->makeUserWithPermissions($this->adminTierSlugs(), 'admin');
        $task = $this->makeTask($this->makeProject());

        /** @var TaskAssignmentService $assignmentService */
        $assignmentService = app(TaskAssignmentService::class);
        $assignmentService->assign($task, [$assignee->employee->id], $assignee->employee->id, $admin);

        /** @var TaskWorkflowService $workflow */
        $workflow = app(TaskWorkflowService::class);
        $assignment = TaskAssignment::query()->where('task_id', $task->id)->firstOrFail();

        $this->assertTrue($workflow->canPerform('accept', $assignment, $assignee));
        $workflow->transitionAssignment($assignment, 'accept', $assignee);
        $assignment->refresh();
        $this->assertSame('accepted', $assignment->status->code);

        $workflow->transitionAssignment($assignment->fresh(), 'start', $assignee);
        $assignment->refresh();
        $this->assertSame('in_progress', $assignment->status->code);

        // Task-level status derives from the assignment.
        $this->assertSame('in_progress', $task->fresh()->status->code);
    }

    public function test_assignee_cannot_approve_their_own_review_without_permission(): void
    {
        $assignee = $this->makeUserWithPermissions($this->selfServiceSlugs(), 'employee');
        $admin = $this->makeUserWithPermissions($this->adminTierSlugs(), 'admin');
        $task = $this->makeTask($this->makeProject());

        app(TaskAssignmentService::class)->assign($task, [$assignee->employee->id], $assignee->employee->id, $admin);
        $assignment = TaskAssignment::query()->where('task_id', $task->id)->firstOrFail();

        $workflow = app(TaskWorkflowService::class);
        $workflow->transitionAssignment($assignment, 'accept', $assignee);
        $workflow->transitionAssignment($assignment->fresh(), 'start', $assignee);
        $workflow->transitionAssignment($assignment->fresh(), 'submit_review', $assignee);

        $underReview = $assignment->fresh();
        $this->assertSame('under_review', $underReview->status->code);
        $this->assertFalse($workflow->canPerform('review_approve', $underReview, $assignee));

        $this->expectException(ValidationException::class);
        $workflow->transitionAssignment($underReview, 'review_approve', $assignee);
    }

    public function test_admin_can_approve_review_and_only_owner_can_complete(): void
    {
        $owner = $this->makeUserWithPermissions($this->selfServiceSlugs(), 'employee');
        $other = $this->makeUserWithPermissions($this->selfServiceSlugs(), 'employee2');
        $admin = $this->makeUserWithPermissions($this->adminTierSlugs(), 'admin');
        $task = $this->makeTask($this->makeProject());

        // Team-assign both, owner is $owner->employee.
        app(TaskAssignmentService::class)->assign($task, [$owner->employee->id, $other->employee->id], $owner->employee->id, $admin);
        $ownerAssignment = TaskAssignment::query()->where('task_id', $task->id)->where('employee_id', $owner->employee->id)->firstOrFail();
        $otherAssignment = TaskAssignment::query()->where('task_id', $task->id)->where('employee_id', $other->employee->id)->firstOrFail();

        $workflow = app(TaskWorkflowService::class);
        foreach ([$ownerAssignment, $otherAssignment] as $a) {
            $workflow->transitionAssignment($a->fresh(), 'accept', $a->employee_id === $owner->employee->id ? $owner : $other);
            $workflow->transitionAssignment($a->fresh(), 'start', $a->employee_id === $owner->employee->id ? $owner : $other);
        }

        $workflow->transitionAssignment($ownerAssignment->fresh(), 'submit_review', $owner);

        // The non-owner assignee cannot approve the review.
        $this->assertFalse($workflow->canPerform('review_approve', $ownerAssignment->fresh(), $other));

        // Admin can approve.
        $approved = $workflow->transitionAssignment($ownerAssignment->fresh(), 'review_approve', $admin);
        $this->assertSame('approved', $approved->status->code);

        // Non-owner cannot mark completed, even though they are also an active assignee.
        $this->assertFalse($workflow->canPerform('complete', $approved, $other));

        // Owner can mark completed.
        $this->assertTrue($workflow->canPerform('complete', $approved, $owner));
        $completed = $workflow->transitionAssignment($approved, 'complete', $owner);
        $this->assertSame('completed', $completed->status->code);
        $this->assertSame(100, $completed->progress_percent);
    }

    public function test_task_cannot_be_completed_while_blocked_by_incomplete_dependency(): void
    {
        $owner = $this->makeUserWithPermissions($this->selfServiceSlugs(), 'employee');
        $admin = $this->makeUserWithPermissions($this->adminTierSlugs(), 'admin');
        $project = $this->makeProject();
        $task = $this->makeTask($project);
        $blockingTask = $this->makeTask($project);

        TaskDependency::query()->create(['task_id' => $task->id, 'depends_on_task_id' => $blockingTask->id]);

        app(TaskAssignmentService::class)->assign($task, [$owner->employee->id], $owner->employee->id, $admin);
        $assignment = TaskAssignment::query()->where('task_id', $task->id)->firstOrFail();

        $workflow = app(TaskWorkflowService::class);
        $workflow->transitionAssignment($assignment->fresh(), 'accept', $owner);
        $workflow->transitionAssignment($assignment->fresh(), 'start', $owner);
        $workflow->transitionAssignment($assignment->fresh(), 'submit_review', $owner);
        $approved = $workflow->transitionAssignment($assignment->fresh(), 'review_approve', $admin);

        $this->assertTrue($workflow->hasBlockingDependencies($task));

        $this->expectException(ValidationException::class);
        $workflow->transitionAssignment($approved, 'complete', $owner);
    }

    public function test_transfer_request_accept_reassigns_the_task(): void
    {
        $from = $this->makeUserWithPermissions($this->selfServiceSlugs(), 'employee-from');
        $to = $this->makeUserWithPermissions($this->selfServiceSlugs(), 'employee-to');
        $admin = $this->makeUserWithPermissions($this->adminTierSlugs(), 'admin');
        $task = $this->makeTask($this->makeProject());

        app(TaskAssignmentService::class)->assign($task, [$from->employee->id], $from->employee->id, $admin);
        $assignment = TaskAssignment::query()->where('task_id', $task->id)->firstOrFail();

        $transferService = app(TaskTransferService::class);
        $transfer = $transferService->request($assignment, $to->employee->id, 'Going on leave', $from);
        $this->assertSame('pending', $transfer->status);

        $decided = $transferService->decide($transfer, true, $to);
        $this->assertSame('accepted', $decided->status);

        $this->assertFalse($assignment->fresh()->is_active);
        $newAssignment = TaskAssignment::query()->where('task_id', $task->id)->where('is_active', true)->firstOrFail();
        $this->assertSame($to->employee->id, $newAssignment->employee_id);
        $this->assertTrue($newAssignment->is_owner);
    }

    public function test_transfer_request_reject_keeps_task_with_original_assignee(): void
    {
        $from = $this->makeUserWithPermissions($this->selfServiceSlugs(), 'employee-from');
        $to = $this->makeUserWithPermissions($this->selfServiceSlugs(), 'employee-to');
        $admin = $this->makeUserWithPermissions($this->adminTierSlugs(), 'admin');
        $task = $this->makeTask($this->makeProject());

        app(TaskAssignmentService::class)->assign($task, [$from->employee->id], $from->employee->id, $admin);
        $assignment = TaskAssignment::query()->where('task_id', $task->id)->firstOrFail();

        $transferService = app(TaskTransferService::class);
        $transfer = $transferService->request($assignment, $to->employee->id, 'Going on leave', $from);
        $decided = $transferService->decide($transfer, false, $to, 'Too busy');

        $this->assertSame('rejected', $decided->status);
        $this->assertTrue($assignment->fresh()->is_active);
        $this->assertSame(1, TaskAssignment::query()->where('task_id', $task->id)->where('is_active', true)->count());
    }

    public function test_reopen_requires_permission_and_clears_completed_at(): void
    {
        $owner = $this->makeUserWithPermissions($this->selfServiceSlugs(), 'employee');
        $admin = $this->makeUserWithPermissions($this->adminTierSlugs(), 'admin');
        $task = $this->makeTask($this->makeProject());

        app(TaskAssignmentService::class)->assign($task, [$owner->employee->id], $owner->employee->id, $admin);
        $assignment = TaskAssignment::query()->where('task_id', $task->id)->firstOrFail();

        $workflow = app(TaskWorkflowService::class);
        $workflow->transitionAssignment($assignment->fresh(), 'accept', $owner);
        $workflow->transitionAssignment($assignment->fresh(), 'start', $owner);
        $workflow->transitionAssignment($assignment->fresh(), 'submit_review', $owner);
        $approved = $workflow->transitionAssignment($assignment->fresh(), 'review_approve', $admin);
        $completed = $workflow->transitionAssignment($approved, 'complete', $owner);
        $this->assertNotNull($completed->completed_at);

        // Owner alone cannot reopen.
        $this->assertFalse($workflow->canPerform('reopen', $completed, $owner));

        $reopened = $workflow->transitionAssignment($completed, 'reopen', $admin, ['reason' => 'Needs more work']);
        $this->assertSame('in_progress', $reopened->status->code);
        $this->assertNull($reopened->completed_at);
    }
}

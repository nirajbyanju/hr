<?php

namespace Tests\Feature\Tasks;

use App\Models\TaskAssignment;
use App\Modules\Tasks\Services\TaskAssignmentService;
use App\Modules\Tasks\Services\TaskWorkflowService;
use Tests\TenantTestCase;

/**
 * A Kanban column groups several workflow statuses, so most real drags span more than one
 * transition. These lock in that the board can actually make the common forward moves while
 * still refusing anything the permission matrix forbids.
 */
class TaskKanbanMoveTest extends TenantTestCase
{
    use CreatesTaskFixtures;

    protected function setUp(): void
    {
        parent::setUp();
    }

    private function assignedAssignmentFor($assignee, $admin): TaskAssignment
    {
        $task = $this->makeTask($this->makeProject());
        app(TaskAssignmentService::class)->assign($task, [$assignee->employee->id], $assignee->employee->id, $admin);

        return TaskAssignment::query()->where('task_id', $task->id)->firstOrFail();
    }

    public function test_dragging_an_assigned_card_to_in_progress_runs_accept_then_start(): void
    {
        $assignee = $this->makeUserWithPermissions($this->selfServiceSlugs(), 'assignee');
        $admin = $this->makeUserWithPermissions($this->adminTierSlugs(), 'admin');
        $assignment = $this->assignedAssignmentFor($assignee, $admin);

        $this->assertSame('assigned', $assignment->status->code);

        $path = app(TaskWorkflowService::class)->pathToStatus($assignment, 'in_progress', $assignee);
        $this->assertSame(['accept', 'start'], $path);

        $this->actingAs($assignee)
            ->patchJson(route('tasks.kanban.move', $assignment), ['column' => 'in_progress'])
            ->assertOk();

        $this->assertSame('in_progress', $assignment->fresh()->status->code);
    }

    public function test_dragging_to_hold_asks_for_a_reason_then_succeeds_with_one(): void
    {
        $assignee = $this->makeUserWithPermissions($this->selfServiceSlugs(), 'assignee');
        $admin = $this->makeUserWithPermissions($this->adminTierSlugs(), 'admin');
        $assignment = $this->assignedAssignmentFor($assignee, $admin);

        // assigned -> on_hold is accept + start + hold, and hold demands a reason.
        $response = $this->actingAs($assignee)
            ->patchJson(route('tasks.kanban.move', $assignment), ['column' => 'hold']);

        $response->assertStatus(422)->assertJson(['reason_required' => true]);
        $this->assertSame('assigned', $assignment->fresh()->status->code, 'Nothing should have been applied yet.');

        $this->actingAs($assignee)
            ->patchJson(route('tasks.kanban.move', $assignment), ['column' => 'hold', 'reason' => 'Waiting on copy'])
            ->assertOk();

        $assignment->refresh();
        $this->assertSame('on_hold', $assignment->status->code);

        // The reason is recorded against the step that required it, not smeared across the chain.
        $holdHistory = $assignment->statusHistory()->whereNotNull('reason')->get();
        $this->assertCount(1, $holdHistory);
        $this->assertSame('Waiting on copy', $holdHistory->first()->reason);
    }

    public function test_admin_can_drag_under_review_to_completed_via_approve_then_complete(): void
    {
        $admin = $this->makeUserWithPermissions($this->adminTierSlugs(), 'admin');
        $assignee = $this->makeUserWithPermissions($this->selfServiceSlugs(), 'assignee');
        $assignment = $this->assignedAssignmentFor($assignee, $admin);

        $workflow = app(TaskWorkflowService::class);
        $workflow->transitionAssignment($assignment->fresh(), 'accept', $assignee);
        $workflow->transitionAssignment($assignment->fresh(), 'start', $assignee);
        $workflow->transitionAssignment($assignment->fresh(), 'submit_review', $assignee);
        $this->assertSame('under_review', $assignment->fresh()->status->code);

        $this->actingAs($admin)
            ->patchJson(route('tasks.kanban.move', $assignment), ['column' => 'completed'])
            ->assertOk();

        $this->assertSame('completed', $assignment->fresh()->status->code);
    }

    public function test_plain_assignee_cannot_drag_under_review_to_completed(): void
    {
        $admin = $this->makeUserWithPermissions($this->adminTierSlugs(), 'admin');
        $assignee = $this->makeUserWithPermissions($this->selfServiceSlugs(), 'assignee');
        $assignment = $this->assignedAssignmentFor($assignee, $admin);

        $workflow = app(TaskWorkflowService::class);
        $workflow->transitionAssignment($assignment->fresh(), 'accept', $assignee);
        $workflow->transitionAssignment($assignment->fresh(), 'start', $assignee);
        $workflow->transitionAssignment($assignment->fresh(), 'submit_review', $assignee);

        // review_approve is Admin-only, so no legal chain exists for the assignee.
        $this->assertNull($workflow->pathToStatus($assignment->fresh(), 'completed', $assignee));

        $this->actingAs($assignee)
            ->patchJson(route('tasks.kanban.move', $assignment), ['column' => 'completed'])
            ->assertStatus(422);

        $this->assertSame('under_review', $assignment->fresh()->status->code);
    }

    public function test_non_droppable_column_is_rejected(): void
    {
        $assignee = $this->makeUserWithPermissions($this->selfServiceSlugs(), 'assignee');
        $admin = $this->makeUserWithPermissions($this->adminTierSlugs(), 'admin');
        $assignment = $this->assignedAssignmentFor($assignee, $admin);

        $this->actingAs($assignee)
            ->patchJson(route('tasks.kanban.move', $assignment), ['column' => 'backlog'])
            ->assertStatus(422);
    }

    public function test_multi_step_move_is_atomic_when_a_guard_rejects_a_later_step(): void
    {
        $admin = $this->makeUserWithPermissions($this->adminTierSlugs(), 'admin');
        $assignee = $this->makeUserWithPermissions($this->selfServiceSlugs(), 'assignee');
        $project = $this->makeProject();
        $task = $this->makeTask($project);
        $blocker = $this->makeTask($project);

        \App\Models\TaskDependency::query()->create([
            'task_id' => $task->id,
            'depends_on_task_id' => $blocker->id,
        ]);

        app(TaskAssignmentService::class)->assign($task, [$assignee->employee->id], $assignee->employee->id, $admin);
        $assignment = TaskAssignment::query()->where('task_id', $task->id)->firstOrFail();

        $workflow = app(TaskWorkflowService::class);
        $workflow->transitionAssignment($assignment->fresh(), 'accept', $assignee);
        $workflow->transitionAssignment($assignment->fresh(), 'start', $assignee);
        $workflow->transitionAssignment($assignment->fresh(), 'submit_review', $assignee);

        // Chain is review_approve + complete; complete is blocked by the open dependency, so the
        // whole drag must roll back rather than leaving the card silently sitting at "approved".
        $this->actingAs($admin)
            ->patchJson(route('tasks.kanban.move', $assignment), ['column' => 'completed'])
            ->assertStatus(422);

        $this->assertSame('under_review', $assignment->fresh()->status->code);
    }
}

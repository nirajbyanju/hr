<?php

namespace Tests\Feature\Tasks;

use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskCategory;
use App\Models\TaskChecklist;
use App\Modules\Tasks\Services\TaskAssignmentService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HTTP-level coverage: exercises the real routes/controllers/Blade views end-to-end
 * (this is the layer that a pure service-level test cannot catch — e.g. a Blade
 * compile error only surfaces when the view is actually rendered for a request).
 */
class TaskHttpTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTaskFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_create_form_renders(): void
    {
        $admin = $this->makeUserWithPermissions(array_merge($this->adminTierSlugs(), ['task.create']), 'admin');

        $this->actingAs($admin)->get(route('tasks.create'))->assertOk();
    }

    public function test_store_creates_task_with_team_assignment(): void
    {
        $admin = $this->makeUserWithPermissions(array_merge($this->adminTierSlugs(), ['task.create']), 'admin');
        $memberOne = $this->makeUserWithPermissions($this->selfServiceSlugs(), 'member-one');
        $memberTwo = $this->makeUserWithPermissions($this->selfServiceSlugs(), 'member-two');
        $project = $this->makeProject();
        $priority = \App\Models\TaskPriority::query()->where('code', 'high')->firstOrFail();

        $response = $this->actingAs($admin)->post(route('tasks.store'), [
            'project_id' => $project->id,
            'priority_id' => $priority->id,
            'title' => 'HTTP-created task',
            'employee_ids' => [$memberOne->employee->id, $memberTwo->employee->id],
            'owner_employee_id' => $memberOne->employee->id,
        ]);

        $task = Task::query()->where('title', 'HTTP-created task')->firstOrFail();
        $response->assertRedirect(route('tasks.show', $task));
        $this->assertTrue($task->is_team_task);
        $this->assertSame(2, $task->assignments()->where('is_active', true)->count());
    }

    public function test_show_page_renders_for_a_fully_populated_task(): void
    {
        $admin = $this->makeUserWithPermissions(array_merge($this->adminTierSlugs(), ['task.create']), 'admin');
        $assignee = $this->makeUserWithPermissions($this->selfServiceSlugs(), 'assignee');
        $task = $this->makeTask($this->makeProject());

        app(TaskAssignmentService::class)->assign($task, [$assignee->employee->id], $assignee->employee->id, $admin);
        $task->checklists()->create(['title' => 'Checklist', 'created_by' => $admin->id])
            ->items()->create(['title' => 'First item', 'created_by' => $admin->id]);
        $task->comments()->create(['employee_id' => $admin->employee?->id, 'comment' => 'A comment']);
        $task->tags()->attach(\App\Models\TaskTag::query()->create(['name' => 'urgent-tag'])->id);
        $task->watchers()->create(['employee_id' => $assignee->employee->id, 'created_by' => $admin->id]);

        $response = $this->actingAs($assignee)->get(route('tasks.show', $task));

        $response->assertOk();
        $response->assertSee('First item');
        $response->assertSee('A comment');
    }

    public function test_assignee_can_transition_own_assignment_via_http(): void
    {
        $admin = $this->makeUserWithPermissions(array_merge($this->adminTierSlugs(), ['task.create']), 'admin');
        $assignee = $this->makeUserWithPermissions($this->selfServiceSlugs(), 'assignee');
        $task = $this->makeTask($this->makeProject());

        app(TaskAssignmentService::class)->assign($task, [$assignee->employee->id], $assignee->employee->id, $admin);
        $assignment = TaskAssignment::query()->where('task_id', $task->id)->firstOrFail();

        $response = $this->actingAs($assignee)->patch(route('tasks.assignments.transition', $assignment), [
            'action' => 'accept',
        ]);

        $response->assertRedirect(route('tasks.show', $task->id));
        $this->assertSame('accepted', $assignment->fresh()->status->code);
    }

    public function test_unauthorized_user_cannot_transition_assignment_via_http(): void
    {
        $admin = $this->makeUserWithPermissions(array_merge($this->adminTierSlugs(), ['task.create']), 'admin');
        $assignee = $this->makeUserWithPermissions($this->selfServiceSlugs(), 'assignee');
        $stranger = $this->makeUserWithPermissions(['task.view'], 'stranger');
        $task = $this->makeTask($this->makeProject());

        app(TaskAssignmentService::class)->assign($task, [$assignee->employee->id], $assignee->employee->id, $admin);
        $assignment = TaskAssignment::query()->where('task_id', $task->id)->firstOrFail();

        $response = $this->actingAs($stranger)->patch(route('tasks.assignments.transition', $assignment), [
            'action' => 'accept',
        ]);

        $response->assertForbidden();
        $this->assertSame('assigned', $assignment->fresh()->status->code);
    }

    public function test_checklist_item_toggle_recomputes_task_progress_via_http(): void
    {
        $admin = $this->makeUserWithPermissions(array_merge($this->adminTierSlugs(), ['task.create']), 'admin');
        $task = $this->makeTask($this->makeProject());
        $checklist = TaskChecklist::query()->create(['task_id' => $task->id, 'title' => 'Checklist', 'created_by' => $admin->id]);
        $itemOne = $checklist->items()->create(['title' => 'Item 1', 'created_by' => $admin->id]);
        $checklist->items()->create(['title' => 'Item 2', 'created_by' => $admin->id]);

        $this->actingAs($admin)->patch(route('tasks.checklist-items.toggle', $itemOne))
            ->assertRedirect(route('tasks.show', $task->id));

        $this->assertTrue($itemOne->fresh()->is_checked);
        $this->assertSame(50, $task->fresh()->progress_percent);
    }

    public function test_task_category_crud_via_http(): void
    {
        $admin = $this->makeUserWithPermissions(['task.view', 'task.create', 'task.update', 'task.delete'], 'admin');

        $this->actingAs($admin)->get(route('task-categories.index'))->assertOk();

        $this->actingAs($admin)->post(route('task-categories.store'), [
            'name' => 'Marketing',
            'color' => '#123456',
        ])->assertRedirect(route('task-categories.index'));

        $category = TaskCategory::query()->where('name', 'Marketing')->firstOrFail();

        $this->actingAs($admin)->put(route('task-categories.update', $category), [
            'name' => 'Marketing Updated',
            'color' => '#654321',
        ])->assertRedirect(route('task-categories.index'));

        $this->assertSame('Marketing Updated', $category->fresh()->name);

        $this->actingAs($admin)->delete(route('task-categories.destroy', $category))
            ->assertRedirect(route('task-categories.index'));

        $this->assertSoftDeleted('task_categories', ['id' => $category->id]);
    }
}

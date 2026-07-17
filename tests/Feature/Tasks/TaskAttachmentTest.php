<?php

namespace Tests\Feature\Tasks;

use App\Models\TaskAttachment;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class TaskAttachmentTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTaskFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        // Uploads land in public/ (this app's convention — it has no Storage disks), so clean up.
        foreach (glob(public_path('assets/uploads/tasks/*')) ?: [] as $dir) {
            File::deleteDirectory($dir);
        }

        parent::tearDown();
    }

    public function test_uploading_an_attachment_records_its_metadata(): void
    {
        $admin = $this->makeUserWithPermissions($this->adminTierSlugs(), 'admin');
        $task = $this->makeTask($this->makeProject());

        $response = $this->actingAs($admin)->post(route('tasks.attachments.store', $task), [
            'file' => UploadedFile::fake()->create('spec-sheet.pdf', 120, 'application/pdf'),
        ]);

        $response->assertRedirect(route('tasks.show', $task));

        $attachment = TaskAttachment::query()->where('task_id', $task->id)->firstOrFail();
        $this->assertSame('spec-sheet.pdf', $attachment->title);
        $this->assertSame('pdf', $attachment->file_extension);

        // The metadata has to survive the move off the temp path.
        $this->assertNotNull($attachment->file_mime, 'file_mime must be captured before the temp file is moved.');
        $this->assertGreaterThan(0, (int) $attachment->file_size, 'file_size must be captured before the temp file is moved.');

        $this->assertFileExists(public_path($attachment->file_path));
    }

    public function test_uploaded_image_is_previewable_and_downloadable(): void
    {
        $admin = $this->makeUserWithPermissions($this->adminTierSlugs(), 'admin');
        $task = $this->makeTask($this->makeProject());

        // ->image() needs the GD extension, which this PHP build lacks; a plain fake with an
        // image mime exercises the same preview path.
        $this->actingAs($admin)->post(route('tasks.attachments.store', $task), [
            'file' => UploadedFile::fake()->create('mockup.png', 40, 'image/png'),
        ])->assertRedirect();

        $attachment = TaskAttachment::query()->where('task_id', $task->id)->firstOrFail();
        $this->assertTrue($attachment->isPreviewableImage());

        $this->actingAs($admin)->get(route('tasks.attachments.preview', $attachment))->assertOk();
        $this->actingAs($admin)->get(route('tasks.attachments.download', $attachment))->assertOk();
    }

    public function test_disallowed_file_type_is_rejected(): void
    {
        $admin = $this->makeUserWithPermissions($this->adminTierSlugs(), 'admin');
        $task = $this->makeTask($this->makeProject());

        $this->actingAs($admin)->post(route('tasks.attachments.store', $task), [
            'file' => UploadedFile::fake()->create('payload.exe', 10),
        ])->assertSessionHasErrors('file');

        $this->assertSame(0, TaskAttachment::query()->where('task_id', $task->id)->count());
    }

    public function test_deleting_an_attachment_removes_the_row_and_the_file(): void
    {
        $admin = $this->makeUserWithPermissions($this->adminTierSlugs(), 'admin');
        $task = $this->makeTask($this->makeProject());

        $this->actingAs($admin)->post(route('tasks.attachments.store', $task), [
            'file' => UploadedFile::fake()->create('notes.docx', 20),
        ])->assertRedirect();

        $attachment = TaskAttachment::query()->where('task_id', $task->id)->firstOrFail();
        $absolutePath = public_path($attachment->file_path);
        $this->assertFileExists($absolutePath);

        $this->actingAs($admin)->delete(route('tasks.attachments.destroy', $attachment))
            ->assertRedirect(route('tasks.show', $task->id));

        $this->assertSoftDeleted('task_attachments', ['id' => $attachment->id]);
        $this->assertFileDoesNotExist($absolutePath);
    }
}

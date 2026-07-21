<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TenantTestCase;

class ProfileTest extends TenantTestCase
{
    protected function tearDown(): void
    {
        // Avatars land in public/ (this app's upload convention), outside the
        // tenant transaction, so remove anything a test wrote.
        foreach (glob(public_path('assets/uploads/users/*')) ?: [] as $file) {
            File::delete($file);
        }

        parent::tearDown();
    }

    private function activeUser(array $attributes = []): User
    {
        $user = new User();

        $user->forceFill(array_merge([
            'name' => 'Tenant Admin',
            'email' => 'admin@testco.test',
            'phone' => null,
            'password' => Hash::make('P@ssword123'),
            'account_status' => 'active',
            'approved_at' => now(),
        ], $attributes))->save();

        return $user;
    }

    public function test_tenant_admin_without_employee_profile_can_open_profile_page(): void
    {
        $user = $this->activeUser();

        $response = $this->actingAs($user)->get(route('dashboard.profile.edit'));

        $response->assertOk();
        $response->assertSee('Profile');
        $response->assertSee('Tenant Admin');
        $response->assertSee('admin@testco.test');
    }

    public function test_tenant_admin_can_update_account_profile(): void
    {
        $user = $this->activeUser();

        $response = $this->actingAs($user)->put(route('dashboard.profile.update'), [
            'name' => 'Updated Admin',
            'phone' => '9800000000',
        ]);

        $response->assertRedirect(route('dashboard.profile.edit'));
        $response->assertSessionHas('success', 'Profile updated successfully.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Admin',
            'phone' => '9800000000',
        ], 'tenant');
    }

    public function test_tenant_admin_can_upload_a_profile_photo(): void
    {
        $user = $this->activeUser();

        // ->image() needs GD, which this PHP build lacks; a fake with an image
        // mime satisfies the `image`/`mimes` rules without it.
        $response = $this->actingAs($user)->put(route('dashboard.profile.update'), [
            'name' => 'Tenant Admin',
            'avatar' => UploadedFile::fake()->create('me.png', 40, 'image/png'),
        ]);

        $response->assertRedirect(route('dashboard.profile.edit'));

        $path = User::query()->whereKey($user->id)->value('avatar_path');
        $this->assertNotNull($path);
        $this->assertStringStartsWith('assets/uploads/users/', (string) $path);
        $this->assertFileExists(public_path($path));
    }

    public function test_uploading_a_new_photo_replaces_and_deletes_the_old_one(): void
    {
        $user = $this->activeUser();

        $this->actingAs($user)->put(route('dashboard.profile.update'), [
            'name' => 'Tenant Admin',
            'avatar' => UploadedFile::fake()->create('first.png', 40, 'image/png'),
        ]);
        $firstPath = User::query()->whereKey($user->id)->value('avatar_path');

        $this->actingAs($user)->put(route('dashboard.profile.update'), [
            'name' => 'Tenant Admin',
            'avatar' => UploadedFile::fake()->create('second.png', 40, 'image/png'),
        ]);
        $secondPath = User::query()->whereKey($user->id)->value('avatar_path');

        $this->assertNotSame($firstPath, $secondPath);
        $this->assertFileDoesNotExist(public_path($firstPath));
        $this->assertFileExists(public_path($secondPath));
    }

    public function test_tenant_admin_can_remove_the_profile_photo(): void
    {
        $user = $this->activeUser();

        $this->actingAs($user)->put(route('dashboard.profile.update'), [
            'name' => 'Tenant Admin',
            'avatar' => UploadedFile::fake()->create('me.png', 40, 'image/png'),
        ]);
        $path = User::query()->whereKey($user->id)->value('avatar_path');
        $this->assertFileExists(public_path($path));

        $this->actingAs($user)->put(route('dashboard.profile.update'), [
            'name' => 'Tenant Admin',
            'remove_avatar' => '1',
        ])->assertRedirect(route('dashboard.profile.edit'));

        $this->assertNull(User::query()->whereKey($user->id)->value('avatar_path'));
        $this->assertFileDoesNotExist(public_path($path));
    }
}

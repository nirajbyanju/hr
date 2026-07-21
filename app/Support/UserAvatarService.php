<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Stores account (User) profile photos. Mirrors EmployeeAssetService: files go
 * under public/assets/uploads/users/ with a randomised name, and the relative
 * path is what gets persisted. Kept separate from the employee avatar so the
 * two never share or delete each other's files.
 */
class UserAvatarService
{
    private const DIRECTORY = 'assets/uploads/users';

    public function store(?UploadedFile $file): ?string
    {
        if ($file === null || ! $file->isValid()) {
            return null;
        }

        $uploadDir = public_path(self::DIRECTORY);
        if (! File::exists($uploadDir)) {
            File::makeDirectory($uploadDir, 0755, true);
        }

        $extension = Str::lower($file->getClientOriginalExtension());
        $filename = 'user_'.time().'_'.Str::random(8).'.'.$extension;
        $file->move($uploadDir, $filename);

        return self::DIRECTORY.'/'.$filename;
    }

    public function delete(?string $path): void
    {
        // Guard on the prefix so a stray value can never delete something else.
        if ($path && str_starts_with($path, self::DIRECTORY.'/')) {
            File::delete(public_path($path));
        }
    }
}

<?php

namespace App\Modules\Announcements\Services;

use App\Models\Announcement;
use App\Modules\Announcements\Repositories\AnnouncementRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AnnouncementService
{
    public function __construct(private readonly AnnouncementRepository $announcementRepository)
    {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function createAnnouncement(array $payload, int $createdBy): Announcement
    {
        return DB::transaction(function () use ($payload, $createdBy): Announcement {
            $expiresAt = null;
            if (! empty($payload['expires_at'])) {
                $expiresAt = Carbon::createFromFormat('Y-m-d', (string) $payload['expires_at'])->endOfDay();
            }
            $audienceType = (string) $payload['audience_type'];
            $audienceEmployeeIds = null;
            if ($audienceType === 'employees') {
                $audienceEmployeeIds = collect($payload['audience_employee_ids'] ?? [])
                    ->map(fn ($id): int => (int) $id)
                    ->filter(fn (int $id): bool => $id > 0)
                    ->unique()
                    ->values()
                    ->all();
            }

            return $this->announcementRepository->create([
                'announcement_type' => $payload['announcement_type'],
                'title' => $payload['title'],
                'body' => $payload['body'],
                'audience_type' => $audienceType,
                'audience_employee_ids' => $audienceEmployeeIds,
                'priority' => $payload['priority'] ?? 'normal',
                'expires_at' => $expiresAt,
                'is_pinned' => (bool) ($payload['is_pinned'] ?? false),
                'is_active' => true,
                'approval_status' => 'pending',
                'created_by' => $createdBy,
            ]);
        });
    }

    public function approveAnnouncement(Announcement $announcement, int $approvedBy): Announcement
    {
        return DB::transaction(function () use ($announcement, $approvedBy): Announcement {
            $this->announcementRepository->update($announcement, [
                'approval_status' => 'approved',
                'approved_by' => $approvedBy,
                'approved_at' => now(),
            ]);

            return $announcement->fresh() ?? $announcement;
        });
    }

    public function publishAnnouncement(Announcement $announcement, int $publishedBy): Announcement
    {
        if ($announcement->approval_status !== 'approved') {
            throw ValidationException::withMessages([
                'announcement' => 'You must approve this item before publishing.',
            ]);
        }

        return DB::transaction(function () use ($announcement, $publishedBy): Announcement {
            $publishAt = $announcement->publish_at ?? now();

            $this->announcementRepository->update($announcement, [
                'publish_at' => $publishAt,
                'published_by' => $publishedBy,
                'is_active' => true,
            ]);

            return $announcement->fresh() ?? $announcement;
        });
    }
}

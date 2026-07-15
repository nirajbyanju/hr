<?php

namespace App\Modules\Announcements\Repositories;

use App\Models\Announcement;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class AnnouncementRepository
{
    /**
     * @param array<string, mixed> $filters
     */
    public function paginate(array $filters, bool $includeUnpublished, ?User $viewer = null): LengthAwarePaginator
    {
        $q = trim((string) ($filters['q'] ?? ''));
        $type = (string) ($filters['type'] ?? '');
        $status = (string) ($filters['status'] ?? '');
        $perPage = max(10, min(100, (int) ($filters['per_page'] ?? 20)));

        return Announcement::query()
            ->with(['creator:id,name', 'publisher:id,name', 'approver:id,name'])
            ->when($q !== '', function ($query) use ($q): void {
                $query->where(function ($inner) use ($q): void {
                    $inner->where('title', 'like', "%{$q}%")
                        ->orWhere('body', 'like', "%{$q}%");
                });
            })
            ->when($type !== '', fn ($query) => $query->where('announcement_type', $type))
            ->when(! $includeUnpublished, function ($query): void {
                $query->where('approval_status', 'approved')
                    ->whereNotNull('publish_at')
                    ->where('is_active', true)
                    ->where(function ($inner): void {
                        $inner->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    });
            })
            ->when(! $includeUnpublished, fn ($query) => $this->applyAudienceVisibility($query, $viewer))
            ->when($includeUnpublished && $status !== '', function ($query) use ($status): void {
                if ($status === 'published') {
                    $query->whereNotNull('publish_at');

                    return;
                }

                if ($status === 'expired') {
                    $query->whereNotNull('expires_at')->where('expires_at', '<=', now());

                    return;
                }

                $query->where('approval_status', $status)->whereNull('publish_at');
            })
            ->orderByDesc('is_pinned')
            ->orderByRaw('COALESCE(publish_at, created_at) DESC')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return Collection<int, Announcement>
     */
    public function latestPublished(int $limit = 5, ?User $viewer = null): Collection
    {
        return Announcement::query()
            ->where('approval_status', 'approved')
            ->whereNotNull('publish_at')
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->when(true, fn ($query) => $this->applyAudienceVisibility($query, $viewer))
            ->orderByDesc('is_pinned')
            ->orderByDesc('publish_at')
            ->orderByDesc('id')
            ->limit(max(1, min(20, $limit)))
            ->get();
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function create(array $attributes): Announcement
    {
        return Announcement::query()->create($attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function update(Announcement $announcement, array $attributes): void
    {
        $announcement->update($attributes);
    }

    public function isVisibleToUser(Announcement $announcement, ?User $viewer): bool
    {
        if ($announcement->audience_type !== 'employees') {
            return true;
        }

        $employeeId = $this->resolveViewerEmployeeId($viewer);
        if ($employeeId <= 0) {
            return false;
        }

        $targets = collect($announcement->audience_employee_ids ?? [])
            ->map(fn ($id): int => (int) $id)
            ->all();

        return in_array($employeeId, $targets, true);
    }

    private function applyAudienceVisibility($query, ?User $viewer)
    {
        $employeeId = $this->resolveViewerEmployeeId($viewer);

        return $query->where(function ($audienceQuery) use ($employeeId): void {
            $audienceQuery->where('audience_type', 'all');

            if ($employeeId > 0) {
                $audienceQuery->orWhere(function ($employeeAudienceQuery) use ($employeeId): void {
                    $employeeAudienceQuery
                        ->where('audience_type', 'employees')
                        ->where(function ($jsonQuery) use ($employeeId): void {
                            $jsonQuery
                                ->whereJsonContains('audience_employee_ids', $employeeId)
                                ->orWhereJsonContains('audience_employee_ids', (string) $employeeId);
                        });
                });
            }
        });
    }

    private function resolveViewerEmployeeId(?User $viewer): int
    {
        if (! $viewer) {
            return 0;
        }

        $fromRelation = (int) ($viewer->employee?->id ?? 0);
        if ($fromRelation > 0) {
            return $fromRelation;
        }

        return (int) (Employee::query()
            ->where('user_id', $viewer->id)
            ->value('id') ?? 0);
    }
}

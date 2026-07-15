<?php

namespace App\Modules\Projects\Services;

use App\Models\Project;
use App\Modules\Projects\Repositories\ProjectRepository;
use Illuminate\Support\Facades\DB;

class ProjectService
{
    public function __construct(private readonly ProjectRepository $projectRepository)
    {
    }

    /** @param array<string, mixed> $payload */
    public function createProject(array $payload): Project
    {
        return DB::transaction(function () use ($payload): Project {
            $project = $this->projectRepository->create([
                'name' => $payload['name'],
                'project_code' => $payload['project_code'],
                'team_id' => $payload['team_id'] ?? null,
                'manager_employee_id' => $payload['manager_employee_id'] ?? null,
                'start_date' => $payload['start_date'] ?? null,
                'deadline' => $payload['deadline'] ?? null,
                'budget' => $payload['budget'] ?? null,
                'status' => $payload['status'],
                'progress_percent' => (int) ($payload['progress_percent'] ?? 0),
                'description' => $payload['description'] ?? null,
            ]);

            if (array_key_exists('member_ids', $payload)) {
                $this->syncSelectedMembers($project, $payload['member_ids'] ?? []);
            }

            return $project;
        });
    }

    /** @param array<string, mixed> $payload */
    public function updateProject(Project $project, array $payload): Project
    {
        return DB::transaction(function () use ($project, $payload): Project {
            $this->projectRepository->update($project, [
                'name' => $payload['name'],
                'project_code' => $payload['project_code'],
                'team_id' => $payload['team_id'] ?? null,
                'manager_employee_id' => $payload['manager_employee_id'] ?? null,
                'start_date' => $payload['start_date'] ?? null,
                'deadline' => $payload['deadline'] ?? null,
                'budget' => $payload['budget'] ?? null,
                'status' => $payload['status'],
                'progress_percent' => (int) ($payload['progress_percent'] ?? 0),
                'description' => $payload['description'] ?? null,
            ]);

            if (array_key_exists('member_ids', $payload)) {
                $this->syncSelectedMembers($project, $payload['member_ids'] ?? []);
            }

            return $project->fresh() ?? $project;
        });
    }

    public function deleteProject(Project $project): void
    {
        DB::transaction(function () use ($project): void {
            $project->members()->detach();
            $this->projectRepository->delete($project);
        });
    }

    /** @param array<int, array<string, mixed>> $members */
    public function syncMembers(Project $project, array $members): void
    {
        DB::transaction(function () use ($project, $members): void {
            $syncData = [];
            foreach ($members as $member) {
                $employeeId = (int) ($member['employee_id'] ?? 0);
                if ($employeeId <= 0) {
                    continue;
                }

                $syncData[$employeeId] = [
                    'project_role' => (string) ($member['project_role'] ?? 'member'),
                    'is_billable' => (bool) ($member['is_billable'] ?? true),
                    'hourly_rate' => $member['hourly_rate'] ?? null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ];
            }

            $project->members()->sync($syncData);
        });
    }

    /** @param array<int, mixed> $memberIds */
    private function syncSelectedMembers(Project $project, array $memberIds): void
    {
        $selectedIds = collect($memberIds)
            ->map(fn ($employeeId) => (int) $employeeId)
            ->filter(fn (int $employeeId) => $employeeId > 0)
            ->unique()
            ->values();

        $existingMembers = $project->members()
            ->whereIn('employees.id', $selectedIds->all())
            ->get()
            ->keyBy('id');

        $syncData = [];
        foreach ($selectedIds as $employeeId) {
            $existing = $existingMembers->get($employeeId);

            $syncData[$employeeId] = [
                'project_role' => (string) ($existing?->pivot?->project_role ?? 'member'),
                'is_billable' => (bool) ($existing?->pivot?->is_billable ?? true),
                'hourly_rate' => $existing?->pivot?->hourly_rate,
                'updated_at' => now(),
                'created_at' => now(),
            ];
        }

        $project->members()->sync($syncData);
    }
}

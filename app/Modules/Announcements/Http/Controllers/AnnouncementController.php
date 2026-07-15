<?php

namespace App\Modules\Announcements\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Employee;
use App\Modules\Announcements\Http\Requests\StoreAnnouncementRequest;
use App\Modules\Announcements\Repositories\AnnouncementRepository;
use App\Modules\Announcements\Services\AnnouncementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function __construct(
        private readonly AnnouncementRepository $announcementRepository,
        private readonly AnnouncementService $announcementService
    ) {
    }

    public function index(Request $request): View
    {
        $user = $request->user();

        $canManageStatuses = $user?->hasAnyPermission([
            'announcement.create',
            'announcement.publish',
            'announcement.approve',
        ]) ?? false;

        $filters = [
            'q' => trim((string) $request->input('q')),
            'type' => (string) $request->input('type', ''),
            'status' => $canManageStatuses ? (string) $request->input('status', '') : '',
            'per_page' => max(10, min(100, (int) $request->input('per_page', 20))),
        ];

        return view('hr.announcements.index', [
            'announcements' => $this->announcementRepository->paginate($filters, $canManageStatuses, $user),
            'filters' => $filters,
            'canManageStatuses' => $canManageStatuses,
            'canCreate' => $user?->hasPermission('announcement.create') ?? false,
            'canApprove' => $user?->hasPermission('announcement.approve') ?? false,
            'canPublish' => $user?->hasPermission('announcement.publish') ?? false,
        ]);
    }

    public function create(): View
    {
        return view('hr.announcements.form', [
            'mode' => 'create',
            'employees' => Employee::query()
                ->select(['id', 'employee_code', 'first_name', 'last_name'])
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(),
        ]);
    }

    public function show(Request $request, Announcement $announcement): View
    {
        $user = $request->user();
        $canManageStatuses = $user?->hasAnyPermission([
            'announcement.create',
            'announcement.publish',
            'announcement.approve',
        ]) ?? false;

        if (! $canManageStatuses) {
            $isVisible = $announcement->approval_status === 'approved'
                && $announcement->publish_at !== null
                && $announcement->is_active
                && ($announcement->expires_at === null || $announcement->expires_at->isFuture())
                && $this->announcementRepository->isVisibleToUser($announcement, $user);

            abort_if(! $isVisible, 403, 'This notice/announcement is not available.');
        }

        $audienceEmployees = collect();
        if ($announcement->audience_type === 'employees') {
            $audienceIds = collect($announcement->audience_employee_ids ?? [])
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->values()
                ->all();

            $audienceEmployees = Employee::query()
                ->select(['id', 'employee_code', 'first_name', 'last_name'])
                ->whereIn('id', $audienceIds)
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get();
        }

        return view('hr.announcements.show', [
            'announcement' => $announcement->load(['creator:id,name', 'approver:id,name', 'publisher:id,name']),
            'audienceEmployees' => $audienceEmployees,
        ]);
    }

    public function store(StoreAnnouncementRequest $request): RedirectResponse
    {
        $this->announcementService->createAnnouncement(
            $request->validated(),
            (int) $request->user()->id
        );

        return redirect()->route('announcements.index')->with('success', __('Notice/announcement created and sent for approval.'));
    }

    public function approve(Request $request, Announcement $announcement): RedirectResponse
    {
        if ($announcement->approval_status === 'approved') {
            return redirect()->route('announcements.index')->with('info', __('Item is already approved.'));
        }

        $this->announcementService->approveAnnouncement($announcement, (int) $request->user()->id);

        return redirect()->route('announcements.index')->with('success', __('Notice/announcement approved successfully.'));
    }

    public function publish(Request $request, Announcement $announcement): RedirectResponse
    {
        try {
            $this->announcementService->publishAnnouncement($announcement, (int) $request->user()->id);
        } catch (ValidationException $exception) {
            return redirect()->route('announcements.index')->withErrors($exception->errors());
        }

        return redirect()->route('announcements.index')->with('success', __('Notice/announcement published successfully.'));
    }
}

<?php

namespace App\Modules\Tasks\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\TaskAssignment;
use App\Models\TaskTransferRequest as TaskTransferRequestModel;
use App\Modules\Tasks\Http\Requests\DecideTaskTransferRequest;
use App\Modules\Tasks\Http\Requests\RequestTaskTransferRequest;
use App\Modules\Tasks\Repositories\TaskAssignmentRepository;
use App\Modules\Tasks\Repositories\TaskTransferRepository;
use App\Modules\Tasks\Services\TaskTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaskTransferController extends Controller
{
    public function __construct(
        private readonly TaskTransferRepository $taskTransferRepository,
        private readonly TaskAssignmentRepository $taskAssignmentRepository,
        private readonly TaskTransferService $taskTransferService,
    ) {
    }

    public function index(): View
    {
        return view('hr.tasks.transfers.index', [
            'transfers' => $this->taskTransferRepository->paginateAll(),
        ]);
    }

    public function inbox(Request $request): View
    {
        $employeeId = (int) ($request->user()?->employee?->id ?? 0);

        return view('hr.tasks.transfers.inbox', [
            'transfers' => $employeeId > 0 ? $this->taskTransferRepository->pendingForEmployee($employeeId) : collect(),
        ]);
    }

    public function store(RequestTaskTransferRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $assignment = TaskAssignment::query()->findOrFail($validated['task_assignment_id']);

        abort_if(! $this->taskAssignmentRepository->canAct($assignment, $request->user()), 403);

        $transfer = $this->taskTransferService->request($assignment, (int) $validated['to_employee_id'], (string) $validated['reason'], $request->user());

        return redirect()->route('tasks.show', $transfer->task_id)->with('success', __('Transfer request submitted successfully.'));
    }

    public function decide(DecideTaskTransferRequest $request, TaskTransferRequestModel $transfer): RedirectResponse
    {
        $validated = $request->validated();
        $this->taskTransferService->decide($transfer, $validated['decision'] === 'accept', $request->user(), $validated['note'] ?? null);

        return redirect()->route('tasks.transfers.inbox')->with('success', __('Transfer request updated successfully.'));
    }
}

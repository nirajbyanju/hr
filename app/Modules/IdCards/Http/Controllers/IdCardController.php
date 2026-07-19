<?php

namespace App\Modules\IdCards\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeIdCard;
use App\Modules\IdCards\Services\IdCardService;
use App\Modules\IdCards\Support\IdCardToken;
use App\Modules\IdCards\Support\QrSvg;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class IdCardController extends Controller
{
    public function __construct(private readonly IdCardService $idCardService)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();

        $filters = [
            'q' => trim((string) $request->input('q')),
            'department_id' => (int) $request->input('department_id', 0),
            'card' => (string) $request->input('card', ''),
            'per_page' => max(10, min(100, (int) $request->input('per_page', 20))),
        ];

        $employees = Employee::query()
            ->with(['department:id,name', 'designation:id,name', 'activeIdCard'])
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $term = '%' . $filters['q'] . '%';
                $query->where(function ($sub) use ($term): void {
                    $sub->where('employee_code', 'like', $term)
                        ->orWhere('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term);
                });
            })
            ->when($filters['department_id'] > 0, fn ($query) => $query->where('department_id', $filters['department_id']))
            ->when($filters['card'] === 'with', fn ($query) => $query->whereHas('idCards', fn ($q) => $q->where('status', 'active')))
            ->when($filters['card'] === 'without', fn ($query) => $query->whereDoesntHave('idCards', fn ($q) => $q->where('status', 'active')))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->paginate($filters['per_page'])
            ->withQueryString();

        return view('hr.id-cards.index', [
            'employees' => $employees,
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
            'canGenerate' => $user?->hasAnyPermission(['id_card.generate', 'id_card.manage']) ?? false,
            'canPrint' => $user?->hasAnyPermission(['id_card.print', 'id_card.manage']) ?? false,
            'canManage' => $user?->hasPermission('id_card.manage') ?? false,
        ]);
    }

    public function generate(Request $request, Employee $employee): RedirectResponse
    {
        $card = $this->idCardService->issue(
            $employee,
            (int) $request->user()->id,
            $request->ip(),
            (string) $request->userAgent()
        );

        return redirect()
            ->route('id-cards.preview', $card)
            ->with('success', __('ID card :number generated.', ['number' => $card->card_number]));
    }

    public function preview(Request $request, EmployeeIdCard $card): View
    {
        $card->load(['employee.department:id,name', 'employee.designation:id,name', 'generatedBy:id,name']);
        $logs = $card->printLogs()->with('performedBy:id,name')->latest()->limit(30)->get();

        return view('hr.id-cards.preview', array_merge($this->cardViewData($card, false), [
            'logs' => $logs,
            'canPrint' => $request->user()?->hasAnyPermission(['id_card.print', 'id_card.manage']) ?? false,
            'canManage' => $request->user()?->hasPermission('id_card.manage') ?? false,
        ]));
    }

    public function print(Request $request, EmployeeIdCard $card): View|RedirectResponse
    {
        if (! $card->isActive()) {
            return redirect()->route('id-cards.preview', $card)
                ->with('error', __('This card has been revoked and cannot be printed.'));
        }

        $card->load(['employee.department:id,name', 'employee.designation:id,name']);
        $this->idCardService->recordPrint($card, 'html', (int) $request->user()->id, $request->ip(), (string) $request->userAgent());

        return view('hr.id-cards.print', $this->cardViewData($card, false));
    }

    public function pdf(Request $request, EmployeeIdCard $card): Response|RedirectResponse
    {
        if (! $card->isActive()) {
            return redirect()->route('id-cards.preview', $card)
                ->with('error', __('This card has been revoked and cannot be downloaded.'));
        }

        $card->load(['employee.department:id,name', 'employee.designation:id,name']);
        $this->idCardService->recordPrint($card, 'pdf', (int) $request->user()->id, $request->ip(), (string) $request->userAgent());

        $pdf = Pdf::loadView('hr.id-cards.pdf', $this->cardViewData($card, true))
            ->setPaper([0, 0, 153.07, 242.65], 'portrait'); // 54mm x 85.6mm in points

        return $pdf->download('id-card-' . $card->card_number . '.pdf');
    }

    public function revoke(Request $request, EmployeeIdCard $card): RedirectResponse
    {
        if (! $card->isActive()) {
            return redirect()->route('id-cards.index')->with('info', __('Card is already revoked.'));
        }

        $this->idCardService->revoke($card, (int) $request->user()->id, $request->ip(), (string) $request->userAgent());

        return redirect()->route('id-cards.index')
            ->with('success', __('ID card :number revoked. Its QR can no longer mark attendance.', ['number' => $card->card_number]));
    }

    /**
     * Build the shared view data for the card (preview / print / pdf).
     *
     * @return array<string, mixed>
     */
    private function cardViewData(EmployeeIdCard $card, bool $forPdf): array
    {
        $employee = $card->employee;
        $token = IdCardToken::make((int) $card->employee_id, (string) $card->serial);

        $photo = null;
        if ($employee->avatar_path) {
            $photo = $forPdf ? $this->fileToDataUri($employee->avatar_path) : asset($employee->avatar_path);
        }

        return [
            'card' => $card,
            'employee' => $employee,
            'qrSvg' => QrSvg::render($token, '30mm'),
            'brandName' => config('app.name', 'SamriddhiHR'),
            'photo' => $photo,
        ];
    }

    private function fileToDataUri(string $relativePath): ?string
    {
        $absolute = public_path($relativePath);
        if (! is_file($absolute)) {
            return null;
        }

        $mime = match (strtolower(pathinfo($absolute, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($absolute));
    }
}

<?php

namespace App\Modules\IdCards\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeIdCard;
use App\Modules\IdCards\Services\IdCardService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Self-service view of the signed-in employee's own ID card.
 *
 * Deliberately takes no route parameters: the employee and card are derived
 * from the authenticated user, so there is no id to tamper with and no way to
 * reach another employee's card. The HR-facing IdCardController stays as the
 * only place cards are issued or revoked.
 */
class MyIdCardController extends Controller
{
    public function __construct(private readonly IdCardService $idCardService)
    {
    }

    public function show(Request $request): View
    {
        $employee = $this->employee($request);
        $card = $this->activeCard($employee);

        $data = [
            'employee' => $employee,
            'card' => $card,
            'hasEmployeeRecord' => $employee !== null,
        ];

        if ($card !== null) {
            $data = array_merge($data, $this->idCardService->cardViewData($card, false));
        }

        return view('hr.id-cards.my', $data);
    }

    public function pdf(Request $request): Response|RedirectResponse
    {
        $card = $this->activeCard($this->employee($request));

        if ($card === null) {
            return redirect()->route('my.id-card')
                ->with('error', __('You do not have an active ID card to download.'));
        }

        $this->idCardService->recordPrint(
            $card,
            'pdf',
            (int) $request->user()->id,
            $request->ip(),
            (string) $request->userAgent()
        );

        $pdf = Pdf::loadView('hr.id-cards.pdf', $this->idCardService->cardViewData($card, true))
            ->setPaper([0, 0, 153.07, 242.65], 'portrait'); // 54mm x 85.6mm in points

        return $pdf->download('id-card-' . $card->card_number . '.pdf');
    }

    private function employee(Request $request): ?Employee
    {
        return $request->user()?->employee()
            ->with(['department:id,name', 'designation:id,name'])
            ->first();
    }

    /**
     * Only an active card is ever surfaced — a revoked one is treated the same
     * as never having been issued, so a stale card cannot be re-downloaded.
     */
    private function activeCard(?Employee $employee): ?EmployeeIdCard
    {
        if ($employee === null) {
            return null;
        }

        $card = EmployeeIdCard::query()
            ->where('employee_id', $employee->id)
            ->active()
            ->latest('generated_at')
            ->first();

        $card?->setRelation('employee', $employee);

        return $card;
    }
}

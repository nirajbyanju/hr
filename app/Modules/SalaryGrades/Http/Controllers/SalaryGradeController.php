<?php

namespace App\Modules\SalaryGrades\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SalaryGrade;
use App\Modules\SalaryGrades\Http\Requests\StoreSalaryGradeRequest;
use App\Modules\SalaryGrades\Http\Requests\UpdateSalaryGradeRequest;
use App\Modules\SalaryGrades\Repositories\SalaryGradeRepository;
use App\Modules\SalaryGrades\Services\SalaryGradeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalaryGradeController extends Controller
{
    public function __construct(
        private readonly SalaryGradeRepository $salaryGradeRepository,
        private readonly SalaryGradeService $salaryGradeService
    ) {
    }

    public function index(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->input('q')),
            'status' => (string) $request->input('status', ''),
            'per_page' => max(10, min(100, (int) $request->input('per_page', 20))),
        ];

        return view('hr.salary_grades.index', [
            'salaryGrades' => $this->salaryGradeRepository->paginate($filters),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('hr.salary_grades.form', ['mode' => 'create']);
    }

    public function store(StoreSalaryGradeRequest $request): RedirectResponse
    {
        $this->salaryGradeService->createSalaryGrade($request->validated());

        return redirect()->route('salary-grades.index')->with('success', __('Salary grade created successfully.'));
    }

    public function edit(SalaryGrade $salaryGrade): View
    {
        return view('hr.salary_grades.form', [
            'mode' => 'edit',
            'salaryGrade' => $salaryGrade,
        ]);
    }

    public function update(UpdateSalaryGradeRequest $request, SalaryGrade $salaryGrade): RedirectResponse
    {
        $this->salaryGradeService->updateSalaryGrade($salaryGrade, $request->validated());

        return redirect()->route('salary-grades.index')->with('success', __('Salary grade updated successfully.'));
    }

    public function destroy(SalaryGrade $salaryGrade): RedirectResponse
    {
        $this->salaryGradeService->deleteSalaryGrade($salaryGrade);

        return redirect()->route('salary-grades.index')->with('success', __('Salary grade deleted successfully.'));
    }
}

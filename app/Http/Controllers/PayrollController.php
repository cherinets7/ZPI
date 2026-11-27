<?php

namespace App\Http\Controllers;

use App\Exceptions\PayrollException;
use App\Models\Department;
use App\Models\Payroll;
use App\Models\User;
use App\Services\AuditService;
use App\Services\PayrollService;
use App\Services\PdfService;
use App\Support\EthiopianDateHelper;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function __construct(
        private PayrollService $payrolls,
        private PdfService $pdf,
        private AuditService $audit,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Payroll::class);
        $rows = Payroll::query()
            ->visibleTo($request->user())
            ->with(['user', 'department'])
            ->latest()
            ->paginate(20);

        return view('payrolls.index', ['payrolls' => $rows]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', Payroll::class);
        $department = $request->filled('department_id')
            ? Department::query()->find($request->integer('department_id'))
            : null;
        $employees = $department
            ? User::query()->where('department_id', $department->id)->where('role', 'employee')->where('status', 'active')->orderBy('name')->get()
            : collect();

        return view('payrolls.create', [
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'department' => $department,
            'employees' => $employees,
            'ethYear' => $request->integer('eth_year') ?: EthiopianDateHelper::fromCarbon(now())['year'],
            'ethMonth' => $request->integer('eth_month') ?: EthiopianDateHelper::fromCarbon(now())['month'],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Payroll::class);
        $data = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'eth_year' => ['required', 'integer', 'between:1900,2200'],
            'eth_month' => ['required', 'integer', 'between:1,13'],
            'bonus' => ['nullable', 'array'],
            'bonus.*' => ['nullable', 'numeric'],
        ]);

        $department = Department::query()->findOrFail($data['department_id']);

        try {
            $created = $this->payrolls->calculateDepartment(
                $department,
                (int) $data['eth_year'],
                (int) $data['eth_month'],
                $request->user(),
                $data['bonus'] ?? []
            );
        } catch (PayrollException $exception) {
            return back()->withInput()->withErrors(['payroll' => implode(' ', $exception->errors)]);
        }

        return redirect()->route('payrolls.index')->with('status', __('ui.payroll_calculated', ['count' => count($created)]));
    }

    public function show(Payroll $payroll)
    {
        $this->authorize('view', $payroll);
        $payroll->load(['user', 'department', 'payer']);

        return view('payrolls.show', compact('payroll'));
    }

    public function edit(Payroll $payroll)
    {
        $this->authorize('update', $payroll);
        $payroll->load(['user', 'department']);

        return view('payrolls.edit', [
            'payroll' => $payroll,
            'bonus' => $this->manualBonus($payroll),
        ]);
    }

    public function update(Request $request, Payroll $payroll)
    {
        $this->authorize('update', $payroll);
        $data = $request->validate([
            'bonus' => ['required', 'numeric'],
        ]);
        $before = $payroll->only(['bonus_or_adjustment', 'net_pay']);
        $money = $this->payrolls->compute(
            (float) $payroll->base_salary,
            (float) $payroll->performance_score,
            (float) $payroll->attendance_ratio,
            (float) $data['bonus']
        );
        $payroll->update([
            'bonus_or_adjustment' => $money['bonus_or_adjustment'],
            'net_pay' => $money['net_pay'],
        ]);
        $this->audit->log('payroll.updated', $payroll, $before, $payroll->only(['bonus_or_adjustment', 'net_pay']), $payroll->department_id);

        return redirect()->route('payrolls.show', $payroll)->with('status', __('ui.saved'));
    }

    public function destroy(Payroll $payroll)
    {
        $this->authorize('delete', $payroll);
        $departmentId = $payroll->department_id;
        $before = $payroll->only(['user_id', 'eth_year', 'eth_month', 'net_pay', 'status']);
        $payroll->delete();
        $this->audit->log('payroll.deleted', null, $before, [], $departmentId);

        return redirect()->route('payrolls.index')->with('status', __('ui.deleted'));
    }

    public function pay(Request $request, Payroll $payroll)
    {
        $this->authorize('pay', $payroll);
        try {
            $this->payrolls->markPaid($payroll, $request->user());
        } catch (PayrollException $exception) {
            return back()->withErrors(['payroll' => implode(' ', $exception->errors)]);
        }

        return back()->with('status', __('ui.paid'));
    }

    public function pdf(Payroll $payroll)
    {
        $this->authorize('view', $payroll);
        abort_unless($payroll->status === 'paid', 403);
        $binary = $this->pdf->payslip($payroll);

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="payslip-'.$payroll->id.'.pdf"',
        ]);
    }

    private function manualBonus(Payroll $payroll): float
    {
        $withoutBonus = $this->payrolls->compute(
            (float) $payroll->base_salary,
            (float) $payroll->performance_score,
            (float) $payroll->attendance_ratio,
            0
        );

        return round((float) $payroll->net_pay - $withoutBonus['net_pay'], 2);
    }
}

<?php

namespace App\Http\Controllers;

use App\Exports\ExecutiveExport;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Feedback;
use App\Models\Payroll;
use App\Models\PlanAndReport;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use App\Services\AuditService;
use App\Services\PdfService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ExecutiveController extends Controller
{
    public function __construct(
        private PdfService $pdf,
        private AuditService $audit,
    ) {}

    public function index(Request $request)
    {
        abort_unless($request->user()->isExecutive(), 403);
        $payload = $this->payload();

        return view('executive.index', $payload);
    }

    public function excel(Request $request)
    {
        abort_unless($request->user()->isExecutive(), 403);

        return Excel::download(new ExecutiveExport($this->rows()), 'executive-report.xlsx');
    }

    public function pdf(Request $request)
    {
        abort_unless($request->user()->isExecutive(), 403);
        $payload = $this->payload();
        $document = $this->pdf->executiveDocument($payload);
        $binary = $this->pdf->executive($payload, $document['hash']);
        $this->recordIssued($document);

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="executive-report.pdf"',
        ]);
    }

    public function directive(Request $request)
    {
        abort_unless($request->user()->isExecutive(), 403);
        $data = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'plan_and_report_id' => ['nullable', 'exists:plans_and_reports,id'],
            'comment' => ['required', 'string', 'max:5000'],
        ]);

        $department = Department::query()->findOrFail($data['department_id']);
        $receiverId = $department->head_id ?: ($department->hasValidActingHead() ? $department->acting_head_id : null);
        $feedback = Feedback::query()->create([
            'plan_and_report_id' => $data['plan_and_report_id'] ?? null,
            'department_id' => $department->id,
            'sender_id' => $request->user()->id,
            'receiver_id' => $receiverId,
            'kind' => 'directive',
            'comment' => $data['comment'],
        ]);

        $planId = $data['plan_and_report_id'] ?? null;
        $url = $planId ? route('plans.show', $planId) : route('dashboard');
        $department->leaders()->each(fn (User $user) => $user->notify(new WorkflowNotification(
            __('ui.directive'),
            $data['comment'],
            $url
        )));
        $this->audit->log('directive.sent', $feedback, [], ['comment' => $data['comment']], $department->id);

        return back()->with('status', __('ui.saved'));
    }

    private function payload(): array
    {
        $rows = $this->rows();
        $labels = $rows->pluck('department')->all();
        $scores = $rows->pluck('score')->all();

        return [
            'budgetYear' => Setting::budgetYear(),
            'rows' => $rows,
            'payrollTotal' => (float) Payroll::query()->sum('net_pay'),
            'paidTotal' => (float) Payroll::query()->where('status', 'paid')->sum('net_pay'),
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'chart' => [
                'type' => 'bar',
                'data' => [
                    'labels' => $labels,
                    'datasets' => [[
                        'label' => __('ui.performance'),
                        'data' => $scores,
                        'backgroundColor' => '#0f3d32',
                    ]],
                ],
                'options' => [
                    'responsive' => true,
                    'plugins' => ['legend' => ['display' => false]],
                    'scales' => ['y' => ['beginAtZero' => true]],
                ],
            ],
        ];
    }

    private function recordIssued(array $document): void
    {
        $exists = AuditLog::query()
            ->where('action', 'executive.issued')
            ->where('new_values->verification_hash', $document['hash'])
            ->exists();

        if ($exists) {
            return;
        }

        $this->audit->log('executive.issued', null, [], [
            'verification_hash' => $document['hash'],
            'budget_year' => $document['snapshot']['budget_year'],
            'paid_total' => $document['snapshot']['paid_total'],
            'departments' => count($document['snapshot']['rows']),
        ]);
    }

    private function rows()
    {
        $year = Setting::budgetYear();
        $scores = PlanAndReport::query()
            ->where('status', 'approved')
            ->where('period_type', 'monthly')
            ->where('eth_year', $year)
            ->whereNotNull('performance_score')
            ->selectRaw('department_id, avg(performance_score) as score')
            ->groupBy('department_id')
            ->pluck('score', 'department_id');

        return Department::query()->where('is_active', true)->orderBy('name')->get()->map(function (Department $department) use ($scores) {
            $payroll = (float) Payroll::query()->where('department_id', $department->id)->where('status', 'paid')->sum('net_pay');

            return [
                'department' => $department->name,
                'score' => round((float) ($scores[$department->id] ?? 0), 2),
                'payroll' => round($payroll, 2),
            ];
        })->sortByDesc('score')->values();
    }
}

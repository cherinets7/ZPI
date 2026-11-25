<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Feedback;
use App\Models\KpiIndicator;
use App\Models\PlanAndReport;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use App\Services\AuditService;
use App\Services\PdfService;
use App\Services\RollupService;
use App\Support\EthiopianDateHelper;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class PlanReportController extends Controller
{
    public function __construct(
        private AuditService $audit,
        private RollupService $rollup,
        private PdfService $pdf,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', PlanAndReport::class);

        $plans = PlanAndReport::query()
            ->visibleTo($request->user())
            ->with(['user', 'department', 'kpiIndicator'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('period_type'), fn ($query) => $query->where('period_type', $request->string('period_type')))
            ->when($request->filled('department_id') && $request->user()->seesAllDepartments(), fn ($query) => $query->where('department_id', $request->integer('department_id')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('plans.index', [
            'plans' => $plans,
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', PlanAndReport::class);

        $periodType = $request->query('period_type', 'weekly');
        $budgetYear = Setting::budgetYear();
        $ethPeriod = 1;
        if ($periodType === 'daily') {
            $today = EthiopianDateHelper::fromCarbon(now());
            $budgetYear = EthiopianDateHelper::budgetYearOf(now());
            $ethPeriod = EthiopianDateHelper::encodeDaily($today['month'], $today['day']);
        } elseif ($periodType === 'weekly') {
            $week = EthiopianDateHelper::weekIdentity(now());
            $budgetYear = $week['budget_year'];
            $ethPeriod = $week['week'];
        } elseif ($periodType === 'monthly') {
            $today = EthiopianDateHelper::fromCarbon(now());
            [$budgetYear, $ethPeriod] = EthiopianDateHelper::budgetPosition($today['year'], $today['month']);
        }

        return view('plans.form', [
            'plan' => new PlanAndReport([
                'type' => $periodType === 'daily' ? 'report' : 'plan',
                'period_type' => $periodType,
                'eth_year' => $budgetYear,
                'eth_period' => $ethPeriod,
                'status' => 'draft',
            ]),
            'indicators' => $this->indicators($request->user()),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', PlanAndReport::class);
        $data = $this->validated($request);
        $kpi = $this->ownedIndicator($request->user(), (int) $data['kpi_indicator_id']);
        $bounds = $this->bounds($data['period_type'], (int) $data['eth_year'], (int) $data['eth_period']);

        try {
            $plan = PlanAndReport::query()->create([
                ...$this->payload($data, $kpi),
                'user_id' => $request->user()->id,
                'department_id' => $request->user()->department_id,
                'status' => 'draft',
                'period_start' => $bounds['start']->toDateString(),
                'period_end' => $bounds['end']->toDateString(),
                'is_rollup' => false,
            ]);
        } catch (QueryException) {
            return back()->withInput()->withErrors(['eth_period' => __('ui.duplicate_period')]);
        }

        $this->audit->log('plan.created', $plan, [], $plan->toArray(), $plan->department_id);

        return redirect()->route('plans.show', $plan)->with('status', __('ui.saved'));
    }

    public function show(PlanAndReport $plan)
    {
        $this->authorize('view', $plan);
        $plan->load(['user', 'department', 'kpiIndicator', 'feedbacks.sender', 'feedbacks.receiver', 'approver']);

        return view('plans.show', ['plan' => $plan]);
    }

    public function edit(Request $request, PlanAndReport $plan)
    {
        $this->authorize('update', $plan);

        return view('plans.form', [
            'plan' => $plan,
            'indicators' => $this->indicators($request->user()),
        ]);
    }

    public function update(Request $request, PlanAndReport $plan)
    {
        $this->authorize('update', $plan);
        $data = $this->validated($request);
        $kpi = $this->ownedIndicator($request->user(), (int) $data['kpi_indicator_id']);
        $bounds = $this->bounds($data['period_type'], (int) $data['eth_year'], (int) $data['eth_period']);

        $plan->fill([
            ...$this->payload($data, $kpi),
            'period_start' => $bounds['start']->toDateString(),
            'period_end' => $bounds['end']->toDateString(),
        ])->save();

        $this->audit->log('plan.updated', $plan, [], $plan->toArray(), $plan->department_id);

        return redirect()->route('plans.show', $plan)->with('status', __('ui.saved'));
    }

    public function destroy(PlanAndReport $plan)
    {
        $this->authorize('delete', $plan);
        $this->audit->log('plan.deleted', $plan, $plan->toArray(), [], $plan->department_id);
        $plan->delete();

        return redirect()->route('plans.index')->with('status', __('ui.saved'));
    }

    public function submit(PlanAndReport $plan)
    {
        $this->authorize('submit', $plan);
        $plan->forceFill([
            'status' => 'submitted',
            'submitted_at' => now(),
        ])->save();

        $this->notifyReviewers($plan, 'submitted');
        $this->audit->log('plan.submitted', $plan, [], ['status' => 'submitted'], $plan->department_id);

        return back()->with('status', __('ui.submitted'));
    }

    public function resubmit(PlanAndReport $plan)
    {
        $this->authorize('resubmit', $plan);
        $plan->forceFill([
            'status' => 'submitted',
            'submitted_at' => now(),
        ])->save();
        $this->notifyReviewers($plan, 'resubmitted');
        $this->audit->log('plan.resubmitted', $plan, [], ['status' => 'submitted'], $plan->department_id);

        return back()->with('status', __('ui.resubmitted'));
    }

    public function screen(Request $request, PlanAndReport $plan)
    {
        $this->authorize('screen', $plan);
        $data = $request->validate(['comment' => ['nullable', 'string', 'max:2000']]);
        $plan->forceFill(['status' => 'verified'])->save();
        if (! empty($data['comment'])) {
            $this->writeFeedback($plan, $request->user(), $data['comment'], 'feedback');
        }
        $this->notifyHead($plan, __('ui.forwarded'));
        $this->audit->log('plan.screened', $plan, [], ['status' => 'verified'], $plan->department_id);

        return back()->with('status', __('ui.forwarded'));
    }

    public function approve(Request $request, PlanAndReport $plan)
    {
        $this->authorize('approve', $plan);
        $data = $request->validate(['comment' => ['required', 'string', 'max:2000']]);

        $score = $plan->performance_score;
        if ($plan->type === 'report' && (float) $plan->target_value > 0) {
            $score = round(((float) $plan->achieved_value / (float) $plan->target_value) * 100, 2);
        }

        $plan->forceFill([
            'status' => 'approved',
            'performance_score' => $score,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ])->save();

        $plan->forceFill([
            'verification_hash' => hash_hmac('sha256', 'report|'.$plan->id.'|'.$plan->performance_score, (string) config('app.key')),
        ])->save();

        $this->writeFeedback($plan, $request->user(), $data['comment'], 'feedback');

        if ($plan->period_type === 'weekly' && $plan->type === 'report') {
            $this->rollup->rollupFromWeekly($plan->fresh());
        }

        $plan->user?->notify(new WorkflowNotification(__('ui.approved'), $plan->title, route('plans.show', $plan)));
        $this->audit->log('plan.approved', $plan, [], ['status' => 'approved', 'performance_score' => $score], $plan->department_id);

        return back()->with('status', __('ui.approved'));
    }

    public function reject(Request $request, PlanAndReport $plan)
    {
        $this->authorize('reject', $plan);
        $data = $request->validate(['comment' => ['required', 'string', 'max:2000']]);
        $plan->forceFill(['status' => 'rejected'])->save();
        $this->writeFeedback($plan, $request->user(), $data['comment'], 'feedback');
        $plan->user?->notify(new WorkflowNotification(__('ui.rejected'), $data['comment'], route('plans.show', $plan)));
        $this->audit->log('plan.rejected', $plan, [], ['status' => 'rejected'], $plan->department_id);

        return back()->with('status', __('ui.rejected'));
    }

    public function unlock(PlanAndReport $plan)
    {
        $this->authorize('unlock', $plan);
        $plan->forceFill(['locked_at' => null])->save();
        $this->audit->log('plan.unlocked', $plan, [], ['locked_at' => null], $plan->department_id);

        return back()->with('status', __('ui.unlocked'));
    }

    public function pdf(PlanAndReport $plan)
    {
        $this->authorize('view', $plan);
        abort_unless($plan->status === 'approved', 403);
        $binary = $this->pdf->report($plan);

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="report-'.$plan->id.'.pdf"',
        ]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'kpi_indicator_id' => ['required', 'exists:kpi_indicators,id'],
            'type' => ['required', Rule::in(config('workflow.plan_types'))],
            'period_type' => ['required', Rule::in(config('workflow.period_types'))],
            'eth_year' => ['required', 'integer', 'between:1900,2200'],
            'eth_period' => ['required', 'integer', 'min:1'],
            'title' => ['required', 'string', 'max:255'],
            'target_value' => ['required', 'numeric', 'min:0'],
            'achieved_value' => ['nullable', 'numeric', 'min:0', 'required_if:type,report'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        if ($data['period_type'] === 'daily') {
            $data['type'] = 'report';
        }

        return $data;
    }

    private function payload(array $data, KpiIndicator $kpi): array
    {
        return [
            'kpi_indicator_id' => $kpi->id,
            'type' => $data['type'],
            'period_type' => $data['period_type'],
            'eth_year' => $data['eth_year'],
            'eth_period' => $data['eth_period'],
            'title' => $data['title'],
            'target_value' => $data['target_value'],
            'achieved_value' => $data['type'] === 'report' ? $data['achieved_value'] : null,
            'notes' => $data['notes'] ?? null,
        ];
    }

    private function indicators(User $user)
    {
        return KpiIndicator::query()
            ->where('department_id', $user->department_id)
            ->where('is_active', true)
            ->orderBy('title')
            ->get();
    }

    private function ownedIndicator(User $user, int $id): KpiIndicator
    {
        $kpi = KpiIndicator::query()->where('is_active', true)->findOrFail($id);
        abort_unless((int) $kpi->department_id === (int) $user->department_id, 403);

        return $kpi;
    }

    private function bounds(string $type, int $year, int $period): array
    {
        try {
            return EthiopianDateHelper::periodBounds($type, $year, $period);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'eth_period' => $exception->getMessage(),
            ]);
        }
    }

    private function writeFeedback(PlanAndReport $plan, User $sender, string $comment, string $kind): void
    {
        Feedback::query()->create([
            'plan_and_report_id' => $plan->id,
            'department_id' => $plan->department_id,
            'sender_id' => $sender->id,
            'receiver_id' => $plan->user_id,
            'kind' => $kind,
            'comment' => $comment,
        ]);
    }

    private function notifyReviewers(PlanAndReport $plan, string $key): void
    {
        $role = $plan->period_type === 'daily' ? 'dept_head' : 'dev_plan_specialist';
        $recipients = User::query()
            ->where('department_id', $plan->department_id)
            ->where('role', $role)
            ->where('status', 'active')
            ->get();

        if ($plan->period_type === 'daily') {
            $plan->loadMissing('department');
            $recipients = $recipients->merge($plan->department?->leaders() ?? collect())->unique('id')->values();
        }

        $recipients->each(fn (User $user) => $user->notify(new WorkflowNotification(
            __('ui.'.$key),
            $plan->title,
            route('plans.show', $plan)
        )));
    }

    private function notifyHead(PlanAndReport $plan, string $title): void
    {
        $plan->loadMissing('department');
        $plan->department?->leaders()->each(fn (User $user) => $user->notify(
            new WorkflowNotification($title, $plan->title, route('plans.show', $plan))
        ));
    }
}

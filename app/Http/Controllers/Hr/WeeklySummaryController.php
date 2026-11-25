<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\HrWeeklySummary;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use App\Services\AttendanceService;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class WeeklySummaryController extends Controller
{
    public function __construct(
        private AttendanceService $attendance,
        private AuditService $audit,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', HrWeeklySummary::class);
        $summaries = HrWeeklySummary::query()
            ->visibleTo($request->user())
            ->with('department')
            ->latest('period_start')
            ->paginate(20);

        return view('hr.weekly.index', compact('summaries'));
    }

    public function show(HrWeeklySummary $summary)
    {
        $this->authorize('view', $summary);
        $summary->load(['department', 'preparer', 'verifier', 'approver']);

        return view('hr.weekly.show', compact('summary'));
    }

    public function generate(Request $request)
    {
        $this->authorize('prepare', HrWeeklySummary::class);
        $data = $request->validate(['date' => ['required', 'date']]);
        $department = $request->user()->department;
        $totals = $this->attendance->summarizeDepartmentWeek($department, Carbon::parse($data['date']));

        $existing = HrWeeklySummary::query()
            ->where('department_id', $department->id)
            ->where('eth_year', $totals['eth_year'])
            ->where('eth_period', $totals['eth_period'])
            ->first();

        if ($existing && in_array($existing->status, ['verified', 'forwarded', 'approved'], true)) {
            return back()->withErrors(['date' => __('ui.week_already_verified')]);
        }

        $summary = HrWeeklySummary::query()->updateOrCreate(
            [
                'department_id' => $department->id,
                'eth_year' => $totals['eth_year'],
                'eth_period' => $totals['eth_period'],
            ],
            [
                'period_start' => $totals['start']->toDateString(),
                'period_end' => $totals['end']->toDateString(),
                'expected_hours' => $totals['expected'],
                'worked_hours' => $totals['worked'],
                'absent_days' => $totals['absent_days'],
                'leave_days' => $totals['leave_days'],
                'holiday_days' => $totals['holiday_days'],
                'status' => 'draft',
                'prepared_by' => $request->user()->id,
                'review_note' => null,
            ]
        );

        $this->audit->log('weekly.generated', $summary, [], $summary->toArray(), $department->id);

        return redirect()->route('hr.weekly.show', $summary)->with('status', __('ui.saved'));
    }

    public function verify(HrWeeklySummary $summary, Request $request)
    {
        $this->authorize('verify', $summary);
        $summary->update([
            'status' => 'verified',
            'verified_by' => $request->user()->id,
        ]);
        $this->audit->log('weekly.verified', $summary, [], ['status' => 'verified'], $summary->department_id);

        return back()->with('status', __('ui.verified'));
    }

    public function forward(HrWeeklySummary $summary)
    {
        $this->authorize('forward', $summary);
        $summary->update(['status' => 'forwarded']);
        $summary->loadMissing('department');
        $summary->department?->leaders()->each(fn (User $user) => $user->notify(new WorkflowNotification(
            __('ui.forwarded'),
            __('ui.weekly').' '.$summary->eth_period,
            route('hr.weekly.show', $summary)
        )));
        $this->audit->log('weekly.forwarded', $summary, [], ['status' => 'forwarded'], $summary->department_id);

        return back()->with('status', __('ui.forwarded'));
    }

    public function approve(Request $request, HrWeeklySummary $summary)
    {
        $this->authorize('decide', $summary);
        $data = $request->validate(['review_note' => ['nullable', 'string', 'max:2000']]);
        $summary->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'review_note' => $data['review_note'] ?? null,
        ]);
        $this->audit->log('weekly.approved', $summary, [], ['status' => 'approved'], $summary->department_id);

        return back()->with('status', __('ui.approved'));
    }

    public function reject(Request $request, HrWeeklySummary $summary)
    {
        $this->authorize('decide', $summary);
        $data = $request->validate(['review_note' => ['required', 'string', 'max:2000']]);
        $summary->update([
            'status' => 'rejected',
            'approved_by' => $request->user()->id,
            'review_note' => $data['review_note'],
        ]);
        $this->audit->log('weekly.rejected', $summary, [], ['status' => 'rejected'], $summary->department_id);

        return back()->with('status', __('ui.rejected'));
    }
}

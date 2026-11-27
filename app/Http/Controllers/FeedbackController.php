<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use App\Models\PlanAndReport;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use App\Services\AuditService;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Feedback::class);
        $feedbacks = Feedback::query()
            ->visibleTo($request->user())
            ->with(['plan', 'sender', 'receiver', 'department'])
            ->latest()
            ->paginate(20);

        return view('feedbacks.index', compact('feedbacks'));
    }

    public function create(Request $request)
    {
        $user = $request->user();
        abort_unless(
            $user->hasRole('dept_head', 'plan_dept', 'zone_chief', 'zone_deputy', 'dev_plan_specialist', 'super_admin')
            || $user->isActingHead()
            || $user->managesDepartment($user->department),
            403
        );
        $plans = PlanAndReport::query()
            ->visibleTo($user)
            ->latest()
            ->limit(100)
            ->get()
            ->filter(fn (PlanAndReport $plan) => $user->can('feedback', $plan))
            ->values();

        return view('feedbacks.form', [
            'feedback' => new Feedback(['kind' => $user->isExecutive() ? 'directive' : 'feedback']),
            'plans' => $plans,
        ]);
    }

    public function store(Request $request, ?PlanAndReport $plan = null)
    {
        if (! $plan) {
            $data = $request->validate([
                'plan_and_report_id' => ['required', 'exists:plans_and_reports,id'],
            ]);
            $plan = PlanAndReport::query()->findOrFail($data['plan_and_report_id']);
        }

        $feedback = $this->write($request, $plan);

        return $request->routeIs('feedbacks.store')
            ? redirect()->route('feedbacks.show', $feedback)->with('status', __('ui.saved'))
            : back()->with('status', __('ui.saved'));
    }

    public function show(Feedback $feedback)
    {
        $this->authorize('view', $feedback);
        $feedback->load(['plan', 'sender', 'receiver', 'department']);

        return view('feedbacks.show', compact('feedback'));
    }

    public function edit(Feedback $feedback)
    {
        $this->authorize('update', $feedback);
        $feedback->load('plan');

        return view('feedbacks.form', [
            'feedback' => $feedback,
            'plans' => collect([$feedback->plan])->filter(),
        ]);
    }

    public function update(Request $request, Feedback $feedback)
    {
        $this->authorize('update', $feedback);
        $isReceiver = (int) $feedback->receiver_id === (int) $request->user()->id;

        if ($request->exists('action_taken')) {
            abort_unless($isReceiver, 403);
            $data = $request->validate([
                'action_taken' => ['required', 'string', 'max:5000'],
            ]);
            $feedback->update(['action_taken' => $data['action_taken']]);
            $this->audit->log('feedback.action', $feedback, [], $data, $feedback->department_id);

            return back()->with('status', __('ui.saved'));
        }

        abort_unless($this->senderCanRevise($request->user(), $feedback), 403);
        $data = $request->validate([
            'comment' => ['required', 'string', 'max:5000'],
        ]);
        $feedback->update(['comment' => $data['comment']]);
        $this->audit->log('feedback.updated', $feedback, [], $data, $feedback->department_id);

        return redirect()->route('feedbacks.show', $feedback)->with('status', __('ui.saved'));
    }

    public function destroy(Feedback $feedback)
    {
        $this->authorize('delete', $feedback);
        $departmentId = $feedback->department_id;
        $before = $feedback->only(['comment', 'kind', 'plan_and_report_id']);
        $feedback->delete();
        $this->audit->log('feedback.deleted', null, $before, [], $departmentId);

        return redirect()->route('feedbacks.index')->with('status', __('ui.deleted'));
    }

    private function write(Request $request, PlanAndReport $plan): Feedback
    {
        $this->authorize('feedback', $plan);
        $data = $request->validate([
            'comment' => ['required', 'string', 'max:5000'],
            'kind' => ['nullable', 'in:feedback,directive'],
        ]);

        $kind = $data['kind'] ?? 'feedback';
        if ($kind === 'directive' && ! $request->user()->isExecutive()) {
            $kind = 'feedback';
        }

        $feedback = Feedback::query()->create([
            'plan_and_report_id' => $plan->id,
            'department_id' => $plan->department_id,
            'sender_id' => $request->user()->id,
            'receiver_id' => $plan->user_id,
            'kind' => $kind,
            'comment' => $data['comment'],
        ]);

        $plan->user?->notify(new WorkflowNotification(
            $kind === 'directive' ? __('ui.directive') : __('ui.feedback'),
            $data['comment'],
            route('feedbacks.show', $feedback)
        ));
        $this->audit->log('feedback.created', $feedback, [], ['comment' => $data['comment']], $plan->department_id);

        return $feedback;
    }

    private function senderCanRevise(mixed $user, Feedback $feedback): bool
    {
        if (! $user instanceof User || $user->role === 'employee') {
            return false;
        }

        if ((int) $feedback->sender_id !== (int) $user->id) {
            return false;
        }

        $plan = $feedback->plan;
        if ($plan && ($plan->status === 'approved' || $plan->isLocked())) {
            return false;
        }

        return true;
    }
}

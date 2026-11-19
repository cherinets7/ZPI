<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use App\Models\PlanAndReport;
use App\Notifications\WorkflowNotification;
use App\Services\AuditService;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function store(Request $request, PlanAndReport $plan)
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
            route('plans.show', $plan)
        ));
        $this->audit->log('feedback.created', $feedback, [], ['comment' => $data['comment']], $plan->department_id);

        return back()->with('status', __('ui.saved'));
    }

    public function update(Request $request, Feedback $feedback)
    {
        abort_unless((int) $feedback->receiver_id === (int) $request->user()->id, 403);
        $data = $request->validate([
            'action_taken' => ['required', 'string', 'max:5000'],
        ]);
        $feedback->update(['action_taken' => $data['action_taken']]);
        $this->audit->log('feedback.action', $feedback, [], $data, $feedback->department_id);

        return back()->with('status', __('ui.saved'));
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class LeaveRequestController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', LeaveRequest::class);
        $leaves = LeaveRequest::query()
            ->visibleTo($request->user())
            ->with(['user', 'department'])
            ->latest()
            ->paginate(15);

        return view('leaves.index', compact('leaves'));
    }

    public function create()
    {
        $this->authorize('create', LeaveRequest::class);

        return view('leaves.form', ['leave' => new LeaveRequest(['type' => 'annual', 'status' => 'draft'])]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', LeaveRequest::class);
        $data = $this->validated($request);
        $leave = LeaveRequest::query()->create([
            ...$data,
            'user_id' => $request->user()->id,
            'department_id' => $request->user()->department_id,
            'days' => $this->days($data['start_date'], $data['end_date']),
            'status' => 'draft',
        ]);
        if ($request->expectsJson()) {
            return response()->json([
                'id' => $leave->id,
                'update_url' => route('leaves.update', $leave),
                'edit_url' => route('leaves.show', $leave),
                'message' => __('ui.autosaved'),
            ]);
        }

        $this->audit->log('leave.created', $leave, [], $leave->toArray(), $leave->department_id);

        return redirect()->route('leaves.show', $leave)->with('status', __('ui.saved'));
    }

    public function show(LeaveRequest $leave)
    {
        $this->authorize('view', $leave);
        $leave->load(['user', 'department', 'reviewer']);

        return view('leaves.show', compact('leave'));
    }

    public function update(Request $request, LeaveRequest $leave)
    {
        $this->authorize('update', $leave);
        $data = $this->validated($request);
        $leave->update([
            ...$data,
            'days' => $this->days($data['start_date'], $data['end_date']),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'id' => $leave->id,
                'update_url' => route('leaves.update', $leave),
                'edit_url' => route('leaves.show', $leave),
                'message' => __('ui.autosaved'),
            ]);
        }

        return redirect()->route('leaves.show', $leave)->with('status', __('ui.saved'));
    }

    public function destroy(LeaveRequest $leave)
    {
        $this->authorize('delete', $leave);
        $leave->delete();

        return redirect()->route('leaves.index')->with('status', __('ui.saved'));
    }

    public function submit(LeaveRequest $leave)
    {
        $this->authorize('submit', $leave);
        $leave->update(['status' => 'submitted']);
        $leave->loadMissing('department');
        $leave->department?->leaders()->each(fn (User $user) => $user->notify(new WorkflowNotification(
            __('ui.submitted'),
            $leave->user?->name.' — '.__('ui.'.$leave->type),
            route('leaves.show', $leave)
        )));
        $this->audit->log('leave.submitted', $leave, [], ['status' => 'submitted'], $leave->department_id);

        return back()->with('status', __('ui.submitted'));
    }

    public function approve(Request $request, LeaveRequest $leave)
    {
        $this->authorize('decide', $leave);
        $data = $request->validate(['review_note' => ['nullable', 'string', 'max:2000']]);
        $leave->update([
            'status' => 'approved',
            'reviewer_id' => $request->user()->id,
            'review_note' => $data['review_note'] ?? null,
        ]);
        $leave->user?->notify(new WorkflowNotification(__('ui.approved'), $leave->review_note ?? '', route('leaves.show', $leave)));
        $this->audit->log('leave.approved', $leave, [], ['status' => 'approved'], $leave->department_id);

        return back()->with('status', __('ui.approved'));
    }

    public function reject(Request $request, LeaveRequest $leave)
    {
        $this->authorize('decide', $leave);
        $data = $request->validate(['review_note' => ['required', 'string', 'max:2000']]);
        $leave->update([
            'status' => 'rejected',
            'reviewer_id' => $request->user()->id,
            'review_note' => $data['review_note'],
        ]);
        $leave->user?->notify(new WorkflowNotification(__('ui.rejected'), $data['review_note'], route('leaves.show', $leave)));
        $this->audit->log('leave.rejected', $leave, [], ['status' => 'rejected'], $leave->department_id);

        return back()->with('status', __('ui.rejected'));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(config('workflow.leave_types'))],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:5000'],
        ]);

        return $data;
    }

    private function days(string $start, string $end): float
    {
        return Carbon::parse($start)->startOfDay()->diffInDays(Carbon::parse($end)->startOfDay()) + 1;
    }
}

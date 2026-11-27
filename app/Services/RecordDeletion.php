<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\EmployeeTransfer;
use App\Models\Feedback;
use App\Models\HrDailyLog;
use App\Models\HrWeeklySummary;
use App\Models\KpiIndicator;
use App\Models\LeaveRequest;
use App\Models\Payroll;
use App\Models\PlanAndReport;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RecordDeletion
{
    public function department(Department $department): void
    {
        DB::transaction(function () use ($department) {
            $id = (int) $department->id;
            $kpiIds = KpiIndicator::query()->where('department_id', $id)->pluck('id');
            $planIds = PlanAndReport::query()
                ->where('department_id', $id)
                ->orWhereIn('kpi_indicator_id', $kpiIds)
                ->pluck('id');

            $this->deleteFeedbacks($planIds, $id);
            PlanAndReport::query()->whereIn('id', $planIds)->delete();
            KpiIndicator::query()->where('department_id', $id)->delete();
            LeaveRequest::query()->where('department_id', $id)->delete();
            HrDailyLog::query()->where('department_id', $id)->delete();
            HrWeeklySummary::query()->where('department_id', $id)->delete();
            Payroll::query()->where('department_id', $id)->delete();
            EmployeeTransfer::query()->where('to_department_id', $id)->delete();
            EmployeeTransfer::query()->where('from_department_id', $id)->update(['from_department_id' => null]);
            User::query()->where('department_id', $id)->update(['department_id' => null]);
            AuditLog::query()->where('department_id', $id)->update(['department_id' => null]);

            $department->delete();
        });
    }

    public function user(User $user): void
    {
        DB::transaction(function () use ($user) {
            $id = (int) $user->id;
            $planIds = PlanAndReport::query()->where('user_id', $id)->pluck('id');

            Feedback::query()->where(function ($query) use ($id, $planIds) {
                $query->where('sender_id', $id);
                if ($planIds->isNotEmpty()) {
                    $query->orWhereIn('plan_and_report_id', $planIds);
                }
            })->delete();
            Feedback::query()->where('receiver_id', $id)->update(['receiver_id' => null]);

            PlanAndReport::query()->where('user_id', $id)->delete();
            PlanAndReport::query()->where('approved_by', $id)->update(['approved_by' => null]);

            LeaveRequest::query()->where('user_id', $id)->delete();
            LeaveRequest::query()->where('reviewer_id', $id)->update(['reviewer_id' => null]);

            HrDailyLog::query()->where('user_id', $id)->delete();
            HrDailyLog::query()->where('recorded_by', $id)->update(['recorded_by' => null]);

            Payroll::query()->where('user_id', $id)->delete();
            Payroll::query()->where('paid_by', $id)->update(['paid_by' => null]);

            EmployeeTransfer::query()->where('user_id', $id)->delete();
            EmployeeTransfer::query()->where('transferred_by', $id)->update(['transferred_by' => null]);

            HrWeeklySummary::query()->where('prepared_by', $id)->update(['prepared_by' => null]);
            HrWeeklySummary::query()->where('verified_by', $id)->update(['verified_by' => null]);
            HrWeeklySummary::query()->where('approved_by', $id)->update(['approved_by' => null]);

            Department::query()->where('head_id', $id)->update(['head_id' => null]);
            Department::query()->where('acting_head_id', $id)->update([
                'acting_head_id' => null,
                'acting_until' => null,
            ]);

            AuditLog::query()->where('user_id', $id)->update(['user_id' => null]);
            DB::table('notifications')
                ->where('notifiable_type', $user->getMorphClass())
                ->where('notifiable_id', $id)
                ->delete();
            DB::table('sessions')->where('user_id', $id)->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            $user->delete();
        });
    }

    public function indicator(KpiIndicator $indicator): void
    {
        DB::transaction(function () use ($indicator) {
            $planIds = PlanAndReport::query()->where('kpi_indicator_id', $indicator->id)->pluck('id');
            $this->deleteFeedbacks($planIds);
            PlanAndReport::query()->whereIn('id', $planIds)->delete();
            $indicator->delete();
        });
    }

    public function plan(PlanAndReport $plan): void
    {
        DB::transaction(function () use ($plan) {
            $this->deleteFeedbacks(collect([$plan->id]));
            $plan->delete();
        });
    }

    /**
     * @param  Collection<int, int>  $planIds
     */
    private function deleteFeedbacks(Collection $planIds, ?int $departmentId = null): void
    {
        if ($planIds->isEmpty() && $departmentId === null) {
            return;
        }

        Feedback::query()->where(function ($query) use ($planIds, $departmentId) {
            $applied = false;
            if ($departmentId !== null) {
                $query->where('department_id', $departmentId);
                $applied = true;
            }
            if ($planIds->isNotEmpty()) {
                $applied
                    ? $query->orWhereIn('plan_and_report_id', $planIds)
                    : $query->whereIn('plan_and_report_id', $planIds);
            }
        })->delete();
    }
}

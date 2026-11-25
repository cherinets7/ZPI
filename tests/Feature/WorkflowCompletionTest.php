<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\HrWeeklySummary;
use App\Models\KpiIndicator;
use App\Models\LeaveRequest;
use App\Models\PlanAndReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class WorkflowCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_acting_head_is_notified_for_the_same_steps_as_the_permanent_head(): void
    {
        $department = $this->department('Health');
        $head = $this->user('dept_head', $department);
        $acting = $this->user('employee', $department);
        $department->update([
            'head_id' => $head->id,
            'acting_head_id' => $acting->id,
            'acting_until' => now()->toDateString(),
        ]);
        $employee = $this->user('employee', $department);
        $planner = $this->user('dev_plan_specialist', $department);
        $hr = $this->user('hr_specialist', $department);
        $chief = $this->user('zone_chief', null);

        $plan = $this->plan($employee, $department, ['status' => 'submitted', 'title' => 'Screened coverage']);
        $this->actingAs($planner)->post(route('plans.screen', $plan))->assertRedirect();
        $this->assertNotified($acting, 'Screened coverage');
        $this->assertNotified($head, 'Screened coverage');

        $daily = $this->plan($employee, $department, [
            'period_type' => 'daily',
            'status' => 'draft',
            'title' => 'Today on the ward',
            'eth_period' => 12,
        ]);
        $this->actingAs($employee)->post(route('plans.submit', $daily))->assertRedirect();
        $this->assertNotified($acting, 'Today on the ward');

        $summary = HrWeeklySummary::query()->create([
            'department_id' => $department->id,
            'eth_year' => 2017,
            'eth_period' => 8,
            'period_start' => '2024-08-26',
            'period_end' => '2024-09-01',
            'expected_hours' => 40,
            'worked_hours' => 40,
            'status' => 'verified',
            'prepared_by' => $hr->id,
        ]);
        $this->actingAs($hr)->post(route('hr.weekly.forward', $summary))->assertRedirect();
        $this->assertNotified($acting, '8');
        $this->assertNotified($head, '8');

        $leave = LeaveRequest::query()->create([
            'user_id' => $employee->id,
            'department_id' => $department->id,
            'type' => 'annual',
            'start_date' => '2024-10-01',
            'end_date' => '2024-10-02',
            'days' => 2,
            'reason' => 'Family visit',
            'status' => 'draft',
        ]);
        $this->actingAs($employee)->post(route('leaves.submit', $leave))->assertRedirect();
        $this->assertNotified($acting, $employee->name);

        $this->actingAs($chief)->post(route('executive.directives.store'), [
            'department_id' => $department->id,
            'comment' => 'Keep the weekly file current.',
        ])->assertRedirect();
        $this->assertNotified($acting, 'Keep the weekly file current.');
        $this->assertNotified($head, 'Keep the weekly file current.');
        $this->assertDatabaseHas('feedbacks', [
            'department_id' => $department->id,
            'receiver_id' => $head->id,
            'kind' => 'directive',
        ]);

        $before = $acting->notifications()->count();
        $department->update(['acting_until' => now()->subDay()->toDateString()]);
        $later = $this->plan($employee, $department, [
            'status' => 'submitted',
            'title' => 'After the acting term',
            'eth_period' => 5,
        ]);
        $this->actingAs($planner)->post(route('plans.screen', $later))->assertRedirect();
        $this->assertSame($before, $acting->fresh()->notifications()->count());
        $this->assertNotified($head, 'After the acting term');
    }

    public function test_executive_pdf_can_be_verified_from_its_qr_hash(): void
    {
        $chief = $this->user('zone_chief', null);

        $first = $this->actingAs($chief)->get(route('executive.pdf'));
        $first->assertOk();
        $first->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $first->getContent());

        $issued = AuditLog::query()->where('action', 'executive.issued')->get();
        $this->assertCount(1, $issued);
        $hash = $issued->first()->new_values['verification_hash'];
        $this->assertNotEmpty($hash);

        $this->get(route('documents.verify', $hash))
            ->assertOk()
            ->assertSee(__('ui.authentic'))
            ->assertSee(__('ui.executive'))
            ->assertSee(__('ui.issued'))
            ->assertSee($hash);

        $this->actingAs($chief)->get(route('executive.pdf'))->assertOk();
        $this->assertSame(1, AuditLog::query()->where('action', 'executive.issued')->count());

        $this->get(route('documents.verify', str_repeat('a', 64)))->assertNotFound();
    }

    public function test_employee_import_reports_invalid_rows_and_keeps_valid_ones(): void
    {
        $department = $this->department('Health');
        $hr = $this->user('hr_specialist', $department);
        $existing = $this->user('employee', $department, ['email' => 'already@example.com']);

        $file = $this->workbook('employees.xlsx', [
            ['name', 'email', 'password', 'base_salary'],
            ['', 'missing-name@example.com', 'Secret@123', 1000],
            ['Valid Person', 'valid.person@example.com', 'Secret@123', 2500],
            ['Duplicate', 'already@example.com', 'Secret@123', 1000],
            ['Bad Mail', 'not-an-email', 'Secret@123', 1000],
        ]);

        $this->actingAs($hr)->post(route('hr.employees.import'), ['file' => $file])
            ->assertRedirect()
            ->assertSessionHasErrors('file')
            ->assertSessionHas('status');

        $this->assertDatabaseHas('users', [
            'email' => 'valid.person@example.com',
            'department_id' => $department->id,
            'role' => 'employee',
        ]);
        $this->assertDatabaseMissing('users', ['email' => 'missing-name@example.com']);
        $this->assertSame(1, User::query()->where('email', $existing->email)->count());
    }

    public function test_kpi_import_reports_invalid_rows_and_keeps_valid_ones(): void
    {
        $department = $this->department('Health');
        $admin = $this->user('super_admin', null);

        $file = $this->workbook('kpis.xlsx', [
            ['department_code', 'title', 'unit', 'weight'],
            ['NOPE', 'Unknown department', 'number', 1],
            [$department->code, '', 'number', 1],
            [$department->code, 'Valid indicator', 'number', 2],
            [$department->code, 'Bad weight', 'number', 'abc'],
        ]);

        $this->actingAs($admin)->post(route('kpis.import'), ['file' => $file])
            ->assertRedirect()
            ->assertSessionHasErrors('file')
            ->assertSessionHas('status');

        $this->assertDatabaseHas('kpi_indicators', [
            'department_id' => $department->id,
            'title' => 'Valid indicator',
        ]);
        $this->assertDatabaseMissing('kpi_indicators', ['title' => 'Unknown department']);
        $this->assertDatabaseMissing('kpi_indicators', ['title' => 'Bad weight']);
        $this->assertSame(1, KpiIndicator::query()->count());
    }

    public function test_leave_edit_stays_on_the_request_page(): void
    {
        $department = $this->department('Health');
        $employee = $this->user('employee', $department);
        $leave = LeaveRequest::query()->create([
            'user_id' => $employee->id,
            'department_id' => $department->id,
            'type' => 'sick',
            'start_date' => '2024-10-01',
            'end_date' => '2024-10-01',
            'days' => 1,
            'reason' => 'Clinic visit',
            'status' => 'draft',
        ]);

        $this->actingAs($employee)->get(route('leaves.show', $leave))
            ->assertOk()
            ->assertSee('#leave-edit', false)
            ->assertDontSee(route('leaves.create'), false);
    }

    private function assertNotified(User $user, string $fragment): void
    {
        $bodies = $user->notifications()->pluck('data')->map(fn ($data) => (string) ($data['body'] ?? ''));
        $this->assertTrue(
            $bodies->contains(fn (string $body) => str_contains($body, $fragment)),
            'Expected a notification containing ['.$fragment.'].'
        );
    }

    private function workbook(string $name, array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray($rows);
        $path = tempnam(sys_get_temp_dir(), 'zpi').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile(
            $path,
            $name,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    private function department(string $name): Department
    {
        return Department::query()->create([
            'name' => $name.' '.Str::upper(Str::random(3)),
            'code' => Str::upper(Str::random(5)),
            'is_active' => true,
        ]);
    }

    private function user(string $role, ?Department $department, array $extra = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'department_id' => $department?->id,
            'status' => 'active',
        ], $extra));
    }

    private function plan(User $owner, Department $department, array $extra = []): PlanAndReport
    {
        $kpi = KpiIndicator::query()->create([
            'department_id' => $department->id,
            'title' => 'Indicator '.Str::random(4),
            'unit' => 'number',
            'weight' => 1,
            'is_active' => true,
        ]);

        return PlanAndReport::query()->create(array_merge([
            'user_id' => $owner->id,
            'department_id' => $department->id,
            'kpi_indicator_id' => $kpi->id,
            'type' => 'plan',
            'period_type' => 'monthly',
            'eth_year' => 2017,
            'eth_period' => 4,
            'title' => 'Coverage plan',
            'target_value' => 100,
            'achieved_value' => null,
            'status' => 'draft',
            'is_rollup' => false,
            'period_start' => '2024-09-11',
            'period_end' => '2024-10-10',
        ], $extra));
    }
}

<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\KpiIndicator;
use App\Models\Payroll;
use App\Models\PlanAndReport;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_theme_is_saved_on_the_user_and_mobile_nav_is_present(): void
    {
        $user = $this->user('super_admin', null);

        $this->actingAs($user)->putJson(route('theme.update'), ['theme' => 'dark'])->assertOk();
        $this->assertSame('dark', $user->fresh()->theme);

        $this->actingAs($user->fresh())->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-bs-theme="dark"', false)
            ->assertSee('id="mobileNav"', false)
            ->assertSee('aria-controls="mobileNav"', false);
    }

    public function test_search_stays_inside_the_records_a_person_can_open(): void
    {
        $health = $this->department('Health');
        $education = $this->department('Education');
        $owner = $this->user('employee', $health);
        $other = $this->user('employee', $education);
        $this->plan($owner, $health, ['title' => 'Own clinic coverage']);
        $this->plan($other, $education, ['title' => 'Secret school coverage']);

        $this->actingAs($owner)->get(route('search', ['q' => 'coverage']))
            ->assertOk()
            ->assertSee('Own clinic coverage')
            ->assertDontSee('Secret school coverage');

        $this->actingAs($owner)->getJson(route('search', ['q' => 'coverage']))
            ->assertOk()
            ->assertJsonFragment(['title' => 'Own clinic coverage'])
            ->assertJsonMissing(['title' => 'Secret school coverage']);
    }

    public function test_empty_plan_list_explains_what_to_do_next(): void
    {
        $employee = $this->user('employee', $this->department('Health'));

        $this->actingAs($employee)->get(route('plans.index'))
            ->assertOk()
            ->assertSee(__('ui.empty_plans_title'))
            ->assertSee(__('ui.empty_plans_body'));
    }

    public function test_notification_feed_updates_without_marking_items_read(): void
    {
        $user = $this->user('employee', $this->department('Health'));
        $user->notify(new WorkflowNotification('Screened coverage', 'Ready for a decision', '/plans/1'));

        $this->actingAs($user)->getJson(route('notifications.feed'))
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('items.0.title', 'Screened coverage');

        $this->assertSame(1, $user->fresh()->unreadNotifications()->count());
    }

    public function test_autosave_keeps_an_unapproved_draft_and_refuses_an_approved_report(): void
    {
        $department = $this->department('Health');
        $employee = $this->user('employee', $department);
        $kpi = KpiIndicator::query()->create([
            'department_id' => $department->id,
            'title' => 'Visits',
            'unit' => 'number',
            'weight' => 1,
            'is_active' => true,
        ]);
        $payload = [
            'kpi_indicator_id' => $kpi->id,
            'type' => 'plan',
            'period_type' => 'monthly',
            'eth_year' => 2017,
            'eth_period' => 4,
            'title' => 'First draft title',
            'target_value' => 10,
            'notes' => 'Still writing',
        ];

        $created = $this->actingAs($employee)->postJson(route('plans.store'), $payload)
            ->assertOk()
            ->assertJsonPath('message', __('ui.autosaved'));
        $plan = PlanAndReport::query()->findOrFail($created->json('id'));
        $this->assertSame('draft', $plan->status);

        $this->putJson(route('plans.update', $plan), [...$payload, 'title' => 'Saved while typing'])
            ->assertOk();
        $this->assertSame('Saved while typing', $plan->fresh()->title);
        $this->assertSame('draft', $plan->fresh()->status);

        $approved = $this->plan($employee, $department, ['status' => 'approved', 'eth_period' => 5]);
        $this->putJson(route('plans.update', $approved), [...$payload, 'eth_period' => 5, 'title' => 'Should not save'])
            ->assertForbidden();
        $this->assertSame('approved', $approved->fresh()->status);
    }

    public function test_payroll_and_report_statuses_use_distinct_badges(): void
    {
        $department = $this->department('Health');
        $finance = $this->user('finance_dept', null);
        $employee = $this->user('employee', $department);
        Payroll::query()->create([
            'user_id' => $employee->id,
            'department_id' => $department->id,
            'eth_year' => 2017,
            'eth_month' => 1,
            'base_salary' => 1000,
            'performance_score' => 80,
            'attendance_ratio' => 1,
            'bonus_or_adjustment' => 0,
            'net_pay' => 1000,
            'status' => 'paid',
        ]);
        $this->plan($employee, $department, ['status' => 'submitted', 'title' => 'Submitted report']);

        $this->actingAs($finance)->get(route('payrolls.index'))
            ->assertOk()
            ->assertSee('badge-status-paid', false);

        $this->actingAs($employee)->get(route('plans.index'))
            ->assertOk()
            ->assertSee('badge-status-submitted', false);
    }

    public function test_two_factor_is_optional_until_an_account_turns_it_on(): void
    {
        $user = $this->user('super_admin', null, ['password' => 'Secret@123']);

        $this->post('/login', ['email' => $user->email, 'password' => 'Secret@123'])
            ->assertRedirect(route('dashboard'));
        $this->post(route('logout'));

        $this->actingAs($user)->post(route('settings.two-factor.start'))->assertRedirect();
        $this->get(route('settings.edit'))->assertOk()->assertSee(__('ui.two_factor_manual'));
        $secret = session('two_factor.setup_secret');
        $this->assertNotEmpty($secret);
        $this->post(route('settings.two-factor.confirm'), [
            'code' => Totp::at($secret, intdiv(time(), 30)),
        ])->assertRedirect()->assertSessionHas('recovery_codes');
        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);

        $this->post(route('logout'));
        $this->post('/login', ['email' => $user->email, 'password' => 'Secret@123'])
            ->assertRedirect(route('two-factor.login'));
        $this->assertGuest();
        $this->post(route('two-factor.store'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post(route('two-factor.store'), [
            'code' => Totp::at($user->fresh()->two_factor_secret, intdiv(time(), 30)),
        ])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_recovery_code_works_once(): void
    {
        $user = $this->user('super_admin', null, ['password' => 'Secret@123']);
        $user->forceFill([
            'two_factor_secret' => Totp::generateSecret(),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => [Hash::make('RECOVERY99')],
        ])->save();

        $this->post('/login', ['email' => $user->email, 'password' => 'Secret@123'])
            ->assertRedirect(route('two-factor.login'));
        $this->post(route('two-factor.store'), ['code' => 'RECOVERY99'])
            ->assertRedirect(route('dashboard'));

        $this->post(route('logout'));
        $this->post('/login', ['email' => $user->email, 'password' => 'Secret@123']);
        $this->post(route('two-factor.store'), ['code' => 'RECOVERY99'])
            ->assertSessionHasErrors('code');
        $this->assertGuest();
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
            'status' => 'draft',
            'is_rollup' => false,
            'period_start' => '2024-09-11',
            'period_end' => '2024-10-10',
        ], $extra));
    }
}

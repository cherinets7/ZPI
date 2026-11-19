<?php

namespace App\Support;

use App\Models\User;

class Navigation
{
    /**
     * @return list<array{label: string, route: string, icon: string, active: string}>
     */
    public static function items(User $user): array
    {
        $items = [
            self::item('ui.dashboard', 'dashboard', 'speedometer2', 'dashboard'),
        ];

        $acting = $user->isActingHead();

        if ($user->hasRole('employee', 'dev_plan_specialist', 'dept_head', 'plan_dept', 'zone_chief', 'zone_deputy', 'super_admin') || $acting) {
            $items[] = self::item('ui.plans_reports', 'plans.index', 'clipboard-data', 'plans.*');
        }

        if ($user->hasRole('employee') || $acting) {
            $items[] = self::item('ui.daily_progress', 'plans.create', 'calendar-day', 'plans.create', ['period_type' => 'daily']);
        }

        if ($user->hasRole('employee', 'dept_head', 'hr_specialist', 'zone_chief', 'zone_deputy', 'super_admin') || $acting) {
            $items[] = self::item('ui.leave_field', 'leaves.index', 'airplane', 'leaves.*');
        }

        if ($user->hasRole('hr_specialist', 'super_admin')) {
            $items[] = self::item('ui.employees', 'hr.employees.index', 'people', 'hr.employees.*');
        }

        if ($user->hasRole('hr_specialist', 'dept_head', 'zone_chief', 'zone_deputy', 'super_admin', 'finance_dept') || $acting) {
            $items[] = self::item('ui.attendance', 'hr.attendance.index', 'calendar-check', 'hr.attendance.*');
            $items[] = self::item('ui.weekly_summaries', 'hr.weekly.index', 'calendar-week', 'hr.weekly.*');
        }

        if ($user->hasRole('employee', 'finance_dept', 'zone_chief', 'zone_deputy', 'super_admin', 'hr_specialist', 'dept_head') || $acting) {
            $items[] = self::item('ui.payroll', 'payrolls.index', 'cash-coin', 'payrolls.*');
        }

        if ($user->hasRole('plan_dept', 'zone_chief', 'zone_deputy', 'super_admin')) {
            $items[] = self::item('ui.departments', 'departments.index', 'building', 'departments.*');
        }

        if ($user->hasRole('zone_chief', 'zone_deputy')) {
            $items[] = self::item('ui.executive', 'executive.index', 'bar-chart', 'executive.*');
        }

        if ($user->hasRole('super_admin', 'dev_plan_specialist', 'dept_head', 'plan_dept', 'zone_chief', 'zone_deputy') || $acting) {
            $items[] = self::item('ui.kpis', 'kpis.index', 'bullseye', 'kpis.*');
        }

        if ($user->hasRole('super_admin', 'hr_specialist', 'dept_head', 'finance_dept') || $acting) {
            $items[] = self::item('ui.holidays', 'holidays.index', 'flag', 'holidays.*');
        }

        if ($user->role === 'super_admin') {
            $items[] = self::item('ui.users', 'admin.users.index', 'person-gear', 'admin.users.*');
            $items[] = self::item('ui.system_settings', 'admin.settings.edit', 'sliders', 'admin.settings.*');
            $items[] = self::item('ui.audit_logs', 'admin.audit.index', 'journal-text', 'admin.audit.*');
        }

        $items[] = self::item('ui.notifications', 'notifications.index', 'bell', 'notifications.*');
        $items[] = self::item('ui.personal_settings', 'settings.edit', 'gear', 'settings.*');

        return $items;
    }

    private static function item(string $label, string $route, string $icon, string $active, array $query = []): array
    {
        return [
            'label' => __($label),
            'route' => $route,
            'icon' => $icon,
            'active' => $active,
            'query' => $query,
        ];
    }
}

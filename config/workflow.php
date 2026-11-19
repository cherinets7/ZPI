<?php

return [

    /*
    | Roles that must belong to an active department. Central roles are not
    | isolated to a single department_id.
    */
    'department_roles' => [
        'employee',
        'hr_specialist',
        'dev_plan_specialist',
        'dept_head',
    ],

    'central_roles' => [
        'finance_dept',
        'plan_dept',
        'zone_chief',
        'zone_deputy',
        'super_admin',
    ],

    'executive_roles' => [
        'zone_chief',
        'zone_deputy',
    ],

    'roles' => [
        'employee',
        'hr_specialist',
        'dev_plan_specialist',
        'dept_head',
        'finance_dept',
        'plan_dept',
        'zone_chief',
        'zone_deputy',
        'super_admin',
    ],

    'period_types' => [
        'daily',
        'weekly',
        'monthly',
        'quarterly',
        'semi_annual',
        'annual',
    ],

    'plan_types' => [
        'plan',
        'report',
    ],

    'plan_statuses' => [
        'draft',
        'submitted',
        'verified',
        'approved',
        'rejected',
    ],

    'leave_types' => [
        'annual',
        'sick',
        'maternity',
        'field_work',
        'unpaid',
        'other',
    ],

    'leave_statuses' => [
        'draft',
        'submitted',
        'approved',
        'rejected',
    ],

    'attendance_statuses' => [
        'present',
        'absent',
        'leave',
        'holiday',
        'field_work',
    ],

    'weekly_statuses' => [
        'draft',
        'verified',
        'forwarded',
        'approved',
        'rejected',
    ],

    'payroll_statuses' => [
        'calculated',
        'paid',
    ],

    'locales' => [
        'en',
        'am',
    ],

    /*
    | Monday = 1 … Friday = 5. Attendance penalties apply only on these days,
    | and only when the day is not a registered holiday or approved leave.
    */
    'working_iso_days' => [1, 2, 3, 4, 5],

    'default_hours_per_day' => 8,
    'default_attendance_weight' => 0.70,
    'default_performance_weight' => 0.30,
    'default_lock_grace_days' => 7,

];

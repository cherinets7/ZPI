<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\AuditService;
use App\Support\EthiopianDateHelper;
use Illuminate\Http\Request;

class SystemSettingController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function edit()
    {
        abort_unless(auth()->user()?->role === 'super_admin', 403);

        return view('admin.settings', [
            'values' => [
                'institution_name' => Setting::getValue('institution_name', 'ZPI'),
                'institution_name_am' => Setting::getValue('institution_name_am', 'ዞን አፈጻጸም'),
                'budget_year' => Setting::getValue('budget_year', EthiopianDateHelper::budgetYearOf(now())),
                'lock_grace_days' => Setting::getValue('lock_grace_days', config('workflow.default_lock_grace_days')),
                'attendance_weight' => Setting::getValue('attendance_weight', config('workflow.default_attendance_weight')),
                'performance_weight' => Setting::getValue('performance_weight', config('workflow.default_performance_weight')),
                'hours_per_day' => Setting::getValue('hours_per_day', config('workflow.default_hours_per_day')),
                'logo_path' => Setting::getValue('logo_path'),
            ],
        ]);
    }

    public function update(Request $request)
    {
        abort_unless($request->user()->role === 'super_admin', 403);
        $data = $request->validate([
            'institution_name' => ['required', 'string', 'max:255'],
            'institution_name_am' => ['required', 'string', 'max:255'],
            'budget_year' => ['required', 'integer', 'between:1900,2200'],
            'lock_grace_days' => ['required', 'integer', 'between:0,60'],
            'attendance_weight' => ['required', 'numeric', 'between:0,1'],
            'performance_weight' => ['required', 'numeric', 'between:0,1'],
            'hours_per_day' => ['required', 'numeric', 'between:1,16'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);

        $attendance = round((float) $data['attendance_weight'], 2);
        $performance = round((float) $data['performance_weight'], 2);
        if (abs(($attendance + $performance) - 1) > 0.001) {
            return back()->withInput()->withErrors([
                'attendance_weight' => __('ui.weights_must_sum'),
            ]);
        }

        foreach (['institution_name', 'institution_name_am', 'budget_year', 'lock_grace_days', 'hours_per_day'] as $key) {
            Setting::putValue($key, $data[$key]);
        }
        Setting::putValue('attendance_weight', $attendance);
        Setting::putValue('performance_weight', $performance);

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('branding', 'public');
            Setting::putValue('logo_path', $path);
        }

        $this->audit->log('settings.updated', null, [], [
            'budget_year' => $data['budget_year'],
            'lock_grace_days' => $data['lock_grace_days'],
            'attendance_weight' => $attendance,
            'performance_weight' => $performance,
        ]);

        return back()->with('status', __('ui.saved'));
    }
}

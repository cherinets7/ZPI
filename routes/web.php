<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\ExecutiveController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\Hr\AttendanceController;
use App\Http\Controllers\Hr\EmployeeController;
use App\Http\Controllers\Hr\WeeklySummaryController;
use App\Http\Controllers\KpiIndicatorController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\PersonalSettingController;
use App\Http\Controllers\PlanReportController;
use App\Http\Controllers\SystemSettingController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VerificationController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:5,1');
    Route::get('forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [ForgotPasswordController::class, 'store'])->middleware('throttle:5,1')->name('password.email');
    Route::get('reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [ResetPasswordController::class, 'store'])->middleware('throttle:5,1')->name('password.update');
});

Route::post('locale', [LocaleController::class, 'update'])->name('locale.update');
Route::get('verify/{hash}', [VerificationController::class, 'show'])->name('documents.verify');

Route::middleware(['auth', 'role'])->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('settings', [PersonalSettingController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [PersonalSettingController::class, 'update'])->name('settings.update');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');

    Route::get('plans', [PlanReportController::class, 'index'])->name('plans.index');
    Route::get('plans/create', [PlanReportController::class, 'create'])->name('plans.create');
    Route::post('plans', [PlanReportController::class, 'store'])->name('plans.store');
    Route::get('plans/{plan}', [PlanReportController::class, 'show'])->name('plans.show');
    Route::get('plans/{plan}/edit', [PlanReportController::class, 'edit'])->name('plans.edit');
    Route::put('plans/{plan}', [PlanReportController::class, 'update'])->name('plans.update');
    Route::delete('plans/{plan}', [PlanReportController::class, 'destroy'])->name('plans.destroy');
    Route::post('plans/{plan}/submit', [PlanReportController::class, 'submit'])->name('plans.submit');
    Route::post('plans/{plan}/resubmit', [PlanReportController::class, 'resubmit'])->name('plans.resubmit');
    Route::post('plans/{plan}/screen', [PlanReportController::class, 'screen'])->name('plans.screen');
    Route::post('plans/{plan}/approve', [PlanReportController::class, 'approve'])->name('plans.approve');
    Route::post('plans/{plan}/reject', [PlanReportController::class, 'reject'])->name('plans.reject');
    Route::post('plans/{plan}/unlock', [PlanReportController::class, 'unlock'])->name('plans.unlock');
    Route::get('plans/{plan}/pdf', [PlanReportController::class, 'pdf'])->name('plans.pdf');
    Route::post('plans/{plan}/feedback', [FeedbackController::class, 'store'])->name('plans.feedback.store');
    Route::put('feedbacks/{feedback}', [FeedbackController::class, 'update'])->name('feedbacks.update');

    Route::get('leaves', [LeaveRequestController::class, 'index'])->name('leaves.index');
    Route::get('leaves/create', [LeaveRequestController::class, 'create'])->name('leaves.create');
    Route::post('leaves', [LeaveRequestController::class, 'store'])->name('leaves.store');
    Route::get('leaves/{leave}', [LeaveRequestController::class, 'show'])->name('leaves.show');
    Route::put('leaves/{leave}', [LeaveRequestController::class, 'update'])->name('leaves.update');
    Route::delete('leaves/{leave}', [LeaveRequestController::class, 'destroy'])->name('leaves.destroy');
    Route::post('leaves/{leave}/submit', [LeaveRequestController::class, 'submit'])->name('leaves.submit');
    Route::post('leaves/{leave}/approve', [LeaveRequestController::class, 'approve'])->name('leaves.approve');
    Route::post('leaves/{leave}/reject', [LeaveRequestController::class, 'reject'])->name('leaves.reject');

    Route::get('hr/employees', [EmployeeController::class, 'index'])->name('hr.employees.index');
    Route::get('hr/employees/create', [EmployeeController::class, 'create'])->name('hr.employees.create');
    Route::post('hr/employees', [EmployeeController::class, 'store'])->name('hr.employees.store');
    Route::get('hr/employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('hr.employees.edit');
    Route::put('hr/employees/{employee}', [EmployeeController::class, 'update'])->name('hr.employees.update');
    Route::get('hr/employees/{employee}/transfer', [EmployeeController::class, 'transferForm'])->name('hr.employees.transfer');
    Route::post('hr/employees/{employee}/transfer', [EmployeeController::class, 'transfer'])->name('hr.employees.transfer.store');
    Route::post('hr/employees/import', [EmployeeController::class, 'import'])->name('hr.employees.import');

    Route::get('hr/attendance', [AttendanceController::class, 'index'])->name('hr.attendance.index');
    Route::post('hr/attendance', [AttendanceController::class, 'store'])->name('hr.attendance.store');

    Route::get('hr/weekly', [WeeklySummaryController::class, 'index'])->name('hr.weekly.index');
    Route::get('hr/weekly/{summary}', [WeeklySummaryController::class, 'show'])->name('hr.weekly.show');
    Route::post('hr/weekly/generate', [WeeklySummaryController::class, 'generate'])->name('hr.weekly.generate');
    Route::post('hr/weekly/{summary}/verify', [WeeklySummaryController::class, 'verify'])->name('hr.weekly.verify');
    Route::post('hr/weekly/{summary}/forward', [WeeklySummaryController::class, 'forward'])->name('hr.weekly.forward');
    Route::post('hr/weekly/{summary}/approve', [WeeklySummaryController::class, 'approve'])->name('hr.weekly.approve');
    Route::post('hr/weekly/{summary}/reject', [WeeklySummaryController::class, 'reject'])->name('hr.weekly.reject');

    Route::get('payrolls', [PayrollController::class, 'index'])->name('payrolls.index');
    Route::get('payrolls/create', [PayrollController::class, 'create'])->name('payrolls.create');
    Route::post('payrolls', [PayrollController::class, 'store'])->name('payrolls.store');
    Route::get('payrolls/{payroll}', [PayrollController::class, 'show'])->name('payrolls.show');
    Route::post('payrolls/{payroll}/pay', [PayrollController::class, 'pay'])->name('payrolls.pay');
    Route::get('payrolls/{payroll}/pdf', [PayrollController::class, 'pdf'])->name('payrolls.pdf');

    Route::get('exports/plans', [ExportController::class, 'plans'])->name('exports.plans');
    Route::get('exports/payrolls', [ExportController::class, 'payrolls'])->name('exports.payrolls');

    Route::get('executive', [ExecutiveController::class, 'index'])->name('executive.index');
    Route::get('executive/excel', [ExecutiveController::class, 'excel'])->name('executive.excel');
    Route::get('executive/pdf', [ExecutiveController::class, 'pdf'])->name('executive.pdf');
    Route::post('executive/directives', [ExecutiveController::class, 'directive'])->name('executive.directives.store');

    Route::get('departments', [DepartmentController::class, 'index'])->name('departments.index');
    Route::get('departments/create', [DepartmentController::class, 'create'])->name('departments.create');
    Route::post('departments', [DepartmentController::class, 'store'])->name('departments.store');
    Route::get('departments/{department}', [DepartmentController::class, 'show'])->name('departments.show');
    Route::get('departments/{department}/edit', [DepartmentController::class, 'edit'])->name('departments.edit');
    Route::put('departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
    Route::delete('departments/{department}', [DepartmentController::class, 'destroy'])->name('departments.destroy');
    Route::post('departments/{department}/acting', [DepartmentController::class, 'acting'])->name('departments.acting');

    Route::get('kpis', [KpiIndicatorController::class, 'index'])->name('kpis.index');
    Route::get('kpis/create', [KpiIndicatorController::class, 'create'])->name('kpis.create');
    Route::post('kpis', [KpiIndicatorController::class, 'store'])->name('kpis.store');
    Route::get('kpis/{kpi}/edit', [KpiIndicatorController::class, 'edit'])->name('kpis.edit');
    Route::put('kpis/{kpi}', [KpiIndicatorController::class, 'update'])->name('kpis.update');
    Route::delete('kpis/{kpi}', [KpiIndicatorController::class, 'destroy'])->name('kpis.destroy');
    Route::post('kpis/import', [KpiIndicatorController::class, 'import'])->name('kpis.import');

    Route::get('holidays', [HolidayController::class, 'index'])->name('holidays.index');
    Route::get('holidays/create', [HolidayController::class, 'create'])->name('holidays.create');
    Route::post('holidays', [HolidayController::class, 'store'])->name('holidays.store');
    Route::get('holidays/{holiday}/edit', [HolidayController::class, 'edit'])->name('holidays.edit');
    Route::put('holidays/{holiday}', [HolidayController::class, 'update'])->name('holidays.update');
    Route::delete('holidays/{holiday}', [HolidayController::class, 'destroy'])->name('holidays.destroy');

    Route::middleware('role:super_admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

        Route::get('settings', [SystemSettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SystemSettingController::class, 'update'])->name('settings.update');

        Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index');
    });
});

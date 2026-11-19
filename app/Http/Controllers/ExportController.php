<?php

namespace App\Http\Controllers;

use App\Exports\PayrollsExport;
use App\Exports\PlansExport;
use App\Models\Payroll;
use App\Models\PlanAndReport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ExportController extends Controller
{
    public function plans(Request $request)
    {
        $this->authorize('export', PlanAndReport::class);
        $rows = PlanAndReport::query()->visibleTo($request->user())->with(['user', 'department', 'kpiIndicator'])->get();

        return Excel::download(new PlansExport($rows), 'plans-and-reports.xlsx');
    }

    public function payrolls(Request $request)
    {
        $this->authorize('viewAny', Payroll::class);
        abort_unless($request->user()->hasRole('finance_dept', 'zone_chief', 'zone_deputy', 'super_admin'), 403);
        $rows = Payroll::query()->visibleTo($request->user())->with(['user', 'department'])->get();

        return Excel::download(new PayrollsExport($rows), 'payrolls.xlsx');
    }
}

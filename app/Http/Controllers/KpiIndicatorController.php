<?php

namespace App\Http\Controllers;

use App\Imports\KpiIndicatorsImport;
use App\Models\Department;
use App\Models\KpiIndicator;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class KpiIndicatorController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', KpiIndicator::class);
        $query = KpiIndicator::query()->with('department')->orderBy('title');
        if (! $request->user()->seesAllDepartments() && $request->user()->role !== 'super_admin') {
            $query->where('department_id', $request->user()->department_id);
        } elseif ($request->filled('department_id')) {
            $query->where('department_id', $request->integer('department_id'));
        }

        return view('kpis.index', [
            'indicators' => $query->paginate(20)->withQueryString(),
            'departments' => Department::query()->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        $this->authorize('create', KpiIndicator::class);

        return view('kpis.form', [
            'indicator' => new KpiIndicator(['is_active' => true, 'weight' => 1, 'unit' => '%']),
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', KpiIndicator::class);
        $indicator = KpiIndicator::query()->create($this->validated($request));
        $this->audit->log('kpi.created', $indicator, [], $indicator->toArray(), $indicator->department_id);

        return redirect()->route('kpis.index')->with('status', __('ui.saved'));
    }

    public function edit(KpiIndicator $kpi)
    {
        $this->authorize('update', $kpi);

        return view('kpis.form', [
            'indicator' => $kpi,
            'departments' => Department::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, KpiIndicator $kpi)
    {
        $this->authorize('update', $kpi);
        $kpi->update($this->validated($request));
        $this->audit->log('kpi.updated', $kpi, [], $kpi->toArray(), $kpi->department_id);

        return redirect()->route('kpis.index')->with('status', __('ui.saved'));
    }

    public function destroy(KpiIndicator $kpi)
    {
        $this->authorize('delete', $kpi);
        $kpi->update(['is_active' => ! $kpi->is_active]);
        $this->audit->log('kpi.toggled', $kpi, [], ['is_active' => $kpi->is_active], $kpi->department_id);

        return back()->with('status', __('ui.saved'));
    }

    public function import(Request $request)
    {
        $this->authorize('create', KpiIndicator::class);
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv']]);
        Excel::import(new KpiIndicatorsImport, $request->file('file'));

        return back()->with('status', __('ui.saved'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'title' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:80'],
            'weight' => ['required', 'numeric', 'min:0'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}

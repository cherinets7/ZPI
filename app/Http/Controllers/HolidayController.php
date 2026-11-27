<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use App\Services\AuditService;
use App\Support\EthiopianDateHelper;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class HolidayController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function index()
    {
        $this->authorize('viewAny', Holiday::class);
        $holidays = Holiday::query()->orderBy('gregorian_date')->paginate(30);

        return view('holidays.index', compact('holidays'));
    }

    public function create()
    {
        $this->authorize('create', Holiday::class);

        return view('holidays.form', ['holiday' => new Holiday(['is_recurring' => true])]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Holiday::class);
        $holiday = Holiday::query()->create($this->validated($request));
        $this->audit->log('holiday.created', $holiday, [], $holiday->toArray());

        return redirect()->route('holidays.index')->with('status', __('ui.saved'));
    }

    public function show(Holiday $holiday)
    {
        $this->authorize('view', $holiday);

        return view('holidays.show', compact('holiday'));
    }

    public function edit(Holiday $holiday)
    {
        $this->authorize('update', $holiday);

        return view('holidays.form', compact('holiday'));
    }

    public function update(Request $request, Holiday $holiday)
    {
        $this->authorize('update', $holiday);
        $holiday->update($this->validated($request));
        $this->audit->log('holiday.updated', $holiday, [], $holiday->toArray());

        return redirect()->route('holidays.index')->with('status', __('ui.saved'));
    }

    public function destroy(Holiday $holiday)
    {
        $this->authorize('delete', $holiday);
        $holiday->delete();

        return back()->with('status', __('ui.saved'));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_am' => ['nullable', 'string', 'max:255'],
            'eth_year' => ['required', 'integer', 'between:1900,2200'],
            'eth_month' => ['required', 'integer', 'between:1,13'],
            'eth_day' => ['required', 'integer', 'between:1,30'],
            'is_recurring' => ['required', 'boolean'],
        ]);

        try {
            $date = EthiopianDateHelper::toCarbon((int) $data['eth_year'], (int) $data['eth_month'], (int) $data['eth_day']);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['eth_day' => $exception->getMessage()]);
        }

        $data['gregorian_date'] = $date->toDateString();

        return $data;
    }
}

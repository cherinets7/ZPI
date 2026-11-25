@extends('layouts.app')
@section('content')
<h1 class="h3">{{ __('ui.attendance') }}</h1>
<p class="text-secondary">{{ __('ui.working_hours') }} {{ __('ui.holiday_excluded') }}</p>
<form class="row g-2 mb-3" method="GET">
    @if($departments->count() > 1)
        <div class="col-md-4">
            <select class="form-select" name="department_id">
                @foreach($departments as $item)
                    <option value="{{ $item->id }}" @selected($item->id === $department->id)>{{ $item->name }}</option>
                @endforeach
            </select>
        </div>
    @endif
    <div class="col-md-3"><input class="form-control" type="date" name="date" value="{{ $week['start']->toDateString() }}"></div>
    <div class="col-md-2"><button class="btn btn-outline-secondary">{{ __('ui.filter') }}</button></div>
</form>
<p>{{ \App\Support\EthiopianDateHelper::periodLabel('weekly', $week['budget_year'], $week['week']) }} ({{ $week['start']->toDateString() }} – {{ $week['end']->toDateString() }})</p>
<form method="POST" action="{{ route('hr.attendance.store') }}">
    @csrf
    <div class="table-responsive card"><table class="table mb-0 align-middle">
        <thead>
        <tr>
            <th>{{ __('ui.name') }}</th>
            @foreach($days as $day)
                <th>{{ $day->format('D d') }}<div class="small fw-normal">{{ \App\Support\EthiopianDateHelper::format($day) }}</div></th>
            @endforeach
        </tr>
        </thead>
        <tbody>
        @php $i = 0; @endphp
        @forelse($employees as $employee)
            <tr>
                <td>{{ $employee->name }}</td>
                @foreach($days as $day)
                    @php
                        $key = $day->toDateString();
                        $log = optional($logs->get($employee->id.'|'.$key))->first();
                        $holiday = $holidays->get($key);
                        $leave = $leaves->first(fn ($item) => $item->user_id === $employee->id && $key >= $item->start_date->toDateString() && $key <= $item->end_date->toDateString());
                        $forced = $holiday ? 'holiday' : ($leave ? ($leave->type === 'field_work' ? 'field_work' : 'leave') : null);
                    @endphp
                    <td style="min-width: 140px;">
                        @if($forced)
                            @include('partials.status', ['status' => $forced])
                        @elseif($canEdit)
                            <input type="hidden" name="rows[{{ $i }}][user_id]" value="{{ $employee->id }}">
                            <input type="hidden" name="rows[{{ $i }}][work_date]" value="{{ $key }}">
                            <select class="form-select form-select-sm mb-1" name="rows[{{ $i }}][status]">
                                @foreach(['present', 'absent', 'field_work'] as $status)
                                    <option value="{{ $status }}" @selected(($log->status ?? 'present') === $status)>{{ __('ui.'.$status) }}</option>
                                @endforeach
                            </select>
                            <input class="form-control form-control-sm" type="number" step="0.5" min="0" max="24" name="rows[{{ $i }}][hours]" value="{{ $log->hours ?? 8 }}">
                            @php $i++; @endphp
                        @else
                            @if($log) @include('partials.status', ['status' => $log->status]) <span class="small">{{ $log->hours }}</span> @else <span class="text-secondary">—</span> @endif
                        @endif
                    </td>
                @endforeach
            </tr>
        @empty
            @include('partials.empty', [
                'columns' => count($days) + 1,
                'title' => __('ui.empty_attendance_title'),
                'body' => __('ui.empty_attendance_body'),
            ])
        @endforelse
        </tbody>
    </table></div>
    @if($canEdit)
        <button class="btn btn-zpi mt-3">{{ __('ui.record_attendance') }}</button>
    @endif
</form>
@endsection

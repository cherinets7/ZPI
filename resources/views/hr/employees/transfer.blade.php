@extends('layouts.app')
@section('content')
<h1 class="h3">{{ __('ui.transfer') }}: {{ $employee->name }}</h1>
<p class="text-secondary">{{ __('ui.transfer_warning') }}</p>
<p>{{ __('ui.from') }}: <strong>{{ $employee->department?->name }}</strong></p>
<form method="POST" action="{{ route('hr.employees.transfer.store', $employee) }}" class="card card-body col-lg-7">
    @csrf
    <div class="mb-3">
        <label class="form-label">{{ __('ui.to') }}</label>
        <select class="form-select" name="to_department_id" required>
            @foreach($departments as $department)
                <option value="{{ $department->id }}">{{ $department->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="mb-3"><label class="form-label">{{ __('ui.effective_on') }}</label><input class="form-control" type="date" name="effective_on" required value="{{ now()->toDateString() }}"></div>
    <div class="mb-3"><label class="form-label">{{ __('ui.reason') }}</label><textarea class="form-control" name="reason" required></textarea></div>
    <button class="btn btn-zpi">{{ __('ui.transfer') }}</button>
</form>
@endsection

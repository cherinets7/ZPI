@extends('layouts.app')
@section('content')
<h1 class="h3">{{ $holiday->exists ? __('ui.edit') : __('ui.create') }}</h1>
<form method="POST" action="{{ $holiday->exists ? route('holidays.update', $holiday) : route('holidays.store') }}" class="card card-body col-lg-7">
    @csrf
    @if($holiday->exists) @method('PUT') @endif
    <div class="mb-3"><label class="form-label">{{ __('ui.name') }}</label><input class="form-control" name="name" value="{{ old('name', $holiday->name) }}" required></div>
    <div class="mb-3"><label class="form-label">{{ __('ui.institution_name_am') }}</label><input class="form-control" name="name_am" value="{{ old('name_am', $holiday->name_am) }}"></div>
    <div class="row">
        <div class="col-md-4 mb-3"><label class="form-label">{{ __('ui.eth_year') }}</label><input class="form-control" type="number" name="eth_year" value="{{ old('eth_year', $holiday->eth_year) }}" required></div>
        <div class="col-md-4 mb-3">
            <label class="form-label">{{ __('ui.eth_month') }}</label>
            <select class="form-select" name="eth_month">
                @for($month = 1; $month <= 13; $month++)
                    <option value="{{ $month }}" @selected((int) old('eth_month', $holiday->eth_month) === $month)>{{ \App\Support\EthiopianDateHelper::monthName($month) }}</option>
                @endfor
            </select>
        </div>
        <div class="col-md-4 mb-3"><label class="form-label">{{ __('ui.days') }}</label><input class="form-control" type="number" name="eth_day" min="1" max="30" value="{{ old('eth_day', $holiday->eth_day) }}" required></div>
    </div>
    <div class="mb-3">
        <select class="form-select" name="is_recurring">
            <option value="1" @selected(old('is_recurring', $holiday->is_recurring ? '1' : '0') == '1')>{{ __('ui.yes') }}</option>
            <option value="0" @selected(old('is_recurring', $holiday->is_recurring ? '1' : '0') == '0')>{{ __('ui.no') }}</option>
        </select>
    </div>
    <button class="btn btn-zpi">{{ __('ui.save') }}</button>
</form>
@endsection

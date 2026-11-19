@extends('layouts.app')
@section('content')
<div class="d-flex justify-content-between mb-3">
    <h1 class="h3">{{ __('ui.payroll') }}</h1>
    <div class="d-flex gap-2">
        @can('create', App\Models\Payroll::class)
            <a class="btn btn-zpi" href="{{ route('payrolls.create') }}">{{ __('ui.calculate') }}</a>
        @endcan
        @if(auth()->user()->hasRole('finance_dept', 'zone_chief', 'zone_deputy', 'super_admin'))
            <a class="btn btn-outline-secondary" href="{{ route('exports.payrolls') }}">{{ __('ui.export_excel') }}</a>
        @endif
    </div>
</div>
<p class="small text-secondary">{{ __('ui.no_flat_deduction') }}</p>
<div class="table-responsive card"><table class="table mb-0">
    <thead><tr><th>{{ __('ui.name') }}</th><th>{{ __('ui.department') }}</th><th>{{ __('ui.period') }}</th><th>{{ __('ui.net_pay') }}</th><th>{{ __('ui.status') }}</th><th></th></tr></thead>
    <tbody>
    @forelse($payrolls as $payroll)
        <tr>
            <td>{{ $payroll->user?->name }}</td>
            <td>{{ $payroll->department?->name }}</td>
            <td>{{ \App\Support\EthiopianDateHelper::monthName((int) $payroll->eth_month) }} {{ $payroll->eth_year }}</td>
            <td>{{ number_format((float) $payroll->net_pay, 2) }}</td>
            <td>@include('partials.status', ['status' => $payroll->status])</td>
            <td><a href="{{ route('payrolls.show', $payroll) }}">{{ __('ui.view') }}</a></td>
        </tr>
    @empty
        <tr><td colspan="6">{{ __('ui.no_records') }}</td></tr>
    @endforelse
    </tbody>
</table></div>
<div class="mt-3">{{ $payrolls->links() }}</div>
@endsection

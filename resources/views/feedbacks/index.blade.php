@extends('layouts.app')
@section('content')
<div class="d-flex justify-content-between mb-3">
    <h1 class="h3">{{ __('ui.feedback') }}</h1>
    @if(auth()->user()->hasRole('dept_head', 'plan_dept', 'zone_chief', 'zone_deputy', 'dev_plan_specialist', 'super_admin') || auth()->user()->isActingHead() || auth()->user()->managesDepartment(auth()->user()->department))
        <a class="btn btn-zpi" href="{{ route('feedbacks.create') }}">{{ __('ui.create') }}</a>
    @endif
</div>
<div class="table-responsive card"><table class="table mb-0">
    <thead><tr><th>{{ __('ui.title') }}</th><th>{{ __('ui.department') }}</th><th>{{ __('ui.comment') }}</th><th>{{ __('ui.status') }}</th><th></th></tr></thead>
    <tbody>
    @forelse($feedbacks as $feedback)
        <tr>
            <td>{{ $feedback->plan?->title ?? '—' }}</td>
            <td>{{ $feedback->department?->name }}</td>
            <td>{{ \Illuminate\Support\Str::limit($feedback->comment, 80) }}</td>
            <td>{{ __('ui.'.$feedback->kind) }}</td>
            <td><a href="{{ route('feedbacks.show', $feedback) }}">{{ __('ui.view') }}</a></td>
        </tr>
    @empty
        @include('partials.empty', [
            'columns' => 5,
            'title' => __('ui.empty_feedback_title'),
            'body' => __('ui.empty_feedback_body'),
        ])
    @endforelse
    </tbody>
</table></div>
<div class="mt-3">{{ $feedbacks->links() }}</div>
@endsection

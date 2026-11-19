@extends('layouts.guest')
@section('title', __('ui.verification'))
@section('content')
<h1 class="h4">{{ __('ui.verification') }}</h1>
<div class="alert alert-success">{{ __('ui.authentic') }}</div>
<dl class="row mb-0">
    <dt class="col-5">{{ __('ui.type') }}</dt><dd class="col-7">{{ $document['kind'] }}</dd>
    <dt class="col-5">{{ __('ui.title') }}</dt><dd class="col-7">{{ $document['title'] }}</dd>
    <dt class="col-5">{{ __('ui.name') }}</dt><dd class="col-7">{{ $document['person'] }}</dd>
    <dt class="col-5">{{ __('ui.department') }}</dt><dd class="col-7">{{ $document['department'] }}</dd>
    <dt class="col-5">{{ __('ui.period') }}</dt><dd class="col-7">{{ $document['period'] }}</dd>
    <dt class="col-5">{{ __('ui.amount') }}</dt><dd class="col-7">{{ $document['detail'] }}</dd>
    <dt class="col-5">{{ __('ui.status') }}</dt><dd class="col-7">{{ __('ui.'.$document['status']) }}</dd>
    <dt class="col-5">{{ __('ui.hash') }}</dt><dd class="col-7 text-break"><code>{{ $document['hash'] }}</code></dd>
</dl>
@endsection

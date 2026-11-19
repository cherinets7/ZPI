@php
    $map = [
        'draft' => 'secondary',
        'submitted' => 'info',
        'verified' => 'primary',
        'forwarded' => 'primary',
        'approved' => 'success',
        'rejected' => 'danger',
        'calculated' => 'warning',
        'paid' => 'success',
        'present' => 'success',
        'absent' => 'danger',
        'leave' => 'info',
        'holiday' => 'secondary',
        'field_work' => 'primary',
        'active' => 'success',
        'inactive' => 'secondary',
    ];
@endphp
<span class="badge text-bg-{{ $map[$status] ?? 'secondary' }}">{{ __('ui.'.$status) }}</span>

<tr>
    <td colspan="{{ $columns }}">
        <div class="empty-state">
            <p class="empty-state-title">{{ $title }}</p>
            <p class="empty-state-body">{{ $body }}</p>
            @if(!empty($action))
                <a class="btn btn-zpi btn-sm" href="{{ $action['url'] }}">{{ $action['label'] }}</a>
            @endif
        </div>
    </td>
</tr>

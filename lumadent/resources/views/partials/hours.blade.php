<dl class="hours-list">
    @foreach (['Monday to Friday' => 'weekdays', 'Saturday' => 'saturday', 'Sunday' => 'sunday'] as $day => $label)
        <div>
            <dt>{{ __('site.'.$label) }}</dt>
            <dd>@if ($profile['opening_hours'][$day] === 'Closed'){{ __('site.closed') }}@else<bdi>{{ $profile['opening_hours'][$day] }}</bdi>@endif</dd>
        </div>
    @endforeach
</dl>

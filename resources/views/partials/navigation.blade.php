@foreach (['services.index' => 'services', 'dentists.index' => 'dentists', 'about' => 'about', 'contact' => 'contact'] as $routeName => $label)
    <a href="{{ $urls->route($routeName) }}" @if ($urls->pageName() === $routeName) aria-current="page" @endif>{{ __('site.'.$label) }}</a>
@endforeach
<a class="button button-small" href="{{ $urls->route('booking.demo') }}" @if ($urls->pageName() === 'booking.demo') aria-current="page" @endif>{{ __('site.book') }} <span class="direction-arrow" aria-hidden="true">↗</span></a>

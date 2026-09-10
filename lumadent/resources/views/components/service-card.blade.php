@props(['service'])
@inject('urls', 'App\Support\LocalizedUrl')
<article class="service-card">
    <span class="card-mark" aria-hidden="true">✦</span>
    <h2><a href="{{ $urls->route('services.show', ['service' => $service['slug']]) }}">{{ $service['name'] }}</a></h2>
    <p>{{ $service['summary'] }}</p>
    <a class="text-link" href="{{ $urls->route('services.show', ['service' => $service['slug']]) }}">{{ __('site.learn_more') }}<span class="sr-only">: {{ $service['name'] }}</span> <span class="direction-arrow" aria-hidden="true">↗</span></a>
</article>

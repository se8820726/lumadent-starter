@inject('urls', 'App\Support\LocalizedUrl')
<x-layout :title="$service['name']" :description="$service['summary']">
    <section class="container section reading-width">
        <a class="text-link" href="{{ $urls->route('services.index') }}">{{ __('site.back_services') }}</a>
        <div class="page-heading"><p class="eyebrow">{{ __('site.services') }}</p><h1>{{ $service['name'] }}</h1><p class="lead">{{ $service['summary'] }}</p></div>
        <div class="prose"><p>{{ $service['description'] }}</p><p class="notice">{{ __('site.service_note') }}</p></div>
        <a class="button" href="{{ $urls->route('booking.demo') }}">{{ __('site.book_cta') }} <span class="direction-arrow" aria-hidden="true">↗</span></a>
    </section>
</x-layout>

@inject('urls', 'App\Support\LocalizedUrl')
<x-layout :title="__('site.book')" :description="__('site.booking_text')">
    <section class="container section reading-width">
        <div class="page-heading"><p class="eyebrow">{{ __('site.book') }}</p><h1>{{ __('site.booking_intro') }}</h1><p class="lead">{{ __('site.booking_text') }}</p></div>
        <p class="notice">{{ __('site.booking_status') }}</p>
        <a class="button" href="{{ $urls->route('services.index') }}">{{ __('site.explore_services') }}</a>
    </section>
</x-layout>

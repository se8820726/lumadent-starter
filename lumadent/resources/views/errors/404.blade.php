@inject('urls', 'App\Support\LocalizedUrl')
<x-layout :title="__('site.not_found')" :noindex="true">
    <section class="container section reading-width">
        <div class="page-heading"><p class="eyebrow">404</p><h1>{{ __('site.not_found') }}</h1><p class="lead">{{ __('site.not_found_text') }}</p></div>
        <a class="button" href="{{ $urls->route('home') }}">{{ __('site.back_home') }}</a>
    </section>
</x-layout>

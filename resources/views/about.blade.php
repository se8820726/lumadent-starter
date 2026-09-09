@inject('urls', 'App\Support\LocalizedUrl')
<x-layout :title="__('site.about')" :description="__('site.about_text')">
    <section class="container section reading-width">
        <div class="page-heading"><p class="eyebrow">{{ __('site.about') }}</p><h1>{{ __('site.about_intro') }}</h1><p class="lead">{{ __('site.about_text') }}</p></div>
        <p class="notice">{{ __('site.about_detail') }}</p>
        <a class="button" href="{{ $urls->route('dentists.index') }}">{{ __('site.step_two') }} <span class="direction-arrow" aria-hidden="true">↗</span></a>
    </section>
</x-layout>

@inject('urls', 'App\Support\LocalizedUrl')
<x-layout :title="__('site.home')">
    <section class="hero container">
        <div class="hero-copy">
            <p class="eyebrow">{{ __('site.hero_eyebrow') }}</p>
            <h1>{{ $clinic['tagline'] }}</h1>
            <p class="lead">{{ __('site.hero_intro') }}</p>
            <div class="actions">
                <a class="button" href="{{ $urls->route('services.index') }}">{{ __('site.explore_services') }} <span class="direction-arrow" aria-hidden="true">↗</span></a>
                <a class="text-link" href="{{ $urls->route('booking.demo') }}">{{ __('site.book_cta') }}</a>
            </div>
        </div>
        <aside class="visit-panel">
            <span class="panel-symbol" aria-hidden="true">✦</span>
            <h2>{{ __('site.welcome') }}</h2>
            <p>{{ __('site.welcome_intro') }}</p>
            <ol class="visit-steps">
                @foreach (['one', 'two', 'three'] as $step)
                    <li><span class="step-number" aria-hidden="true">{{ $loop->iteration }}</span><div><h3>{{ __('site.step_'.$step) }}</h3><p>{{ __('site.step_'.$step.'_text') }}</p></div></li>
                @endforeach
            </ol>
        </aside>
    </section>
    <section class="section section-tint">
        <div class="container">
            <div class="section-heading"><h2>{{ __('site.featured_services') }}</h2><a class="text-link" href="{{ $urls->route('services.index') }}">{{ __('site.all_services') }} <span class="direction-arrow" aria-hidden="true">↗</span></a></div>
            <div class="card-grid">@foreach ($services as $service)<x-service-card :service="$service" />@endforeach</div>
        </div>
    </section>
</x-layout>

@inject('urls', 'App\Support\LocalizedUrl')
<x-layout :title="__('site.home')">
    <section class="home-hero container">
        <div class="hero-copy">
            <p class="eyebrow">{{ __('site.hero_eyebrow') }}</p>
            <h1>{{ $clinic['tagline'] }}</h1>
            <p class="lead">{{ __('site.hero_intro') }}</p>
            <div class="actions">
                <a class="button" href="{{ $urls->route('services.index') }}">{{ __('site.explore_services') }} <span class="direction-arrow" aria-hidden="true">↗</span></a>
                <a class="text-link" href="{{ $urls->route('booking.demo') }}">{{ __('site.book_cta') }}</a>
            </div>
            <p class="hero-concept"><span aria-hidden="true">✦</span> {{ __('site.hero_concept_note') }}</p>
        </div>
        <figure class="hero-media">
            <img src="{{ asset('images/clinic/lumadent-reception.webp') }}" width="1600" height="900" loading="eager" fetchpriority="high" alt="{{ __('site.hero_image_alt') }}">
            <figcaption><span lang="en" dir="ltr">Concept Project</span><span>{{ __('site.central_london') }}</span></figcaption>
        </figure>
    </section>

    <section class="section section-tint">
        <div class="container">
            <div class="section-heading">
                <div><p class="eyebrow">{{ __('site.services') }}</p><h2>{{ __('site.featured_services') }}</h2><p class="section-intro">{{ __('site.services_intro') }}</p></div>
                <a class="text-link" href="{{ $urls->route('services.index') }}">{{ __('site.all_services') }} <span class="direction-arrow" aria-hidden="true">↗</span></a>
            </div>
            <div class="card-grid">@foreach ($services as $service)<x-service-card :service="$service" />@endforeach</div>
        </div>
    </section>

    <section class="section container journey-section">
        <div class="journey-heading">
            <p class="eyebrow">{{ __('site.practical_eyebrow') }}</p>
            <h2>{{ __('site.welcome') }}</h2>
            <p class="lead">{{ __('site.welcome_intro') }}</p>
        </div>
        <ol class="journey-grid">
            @foreach (['one', 'two', 'three'] as $step)
                <li>
                    <span class="journey-number" aria-hidden="true">0{{ $loop->iteration }}</span>
                    <div><h3>{{ __('site.step_'.$step) }}</h3><p>{{ __('site.step_'.$step.'_text') }}</p></div>
                </li>
            @endforeach
        </ol>
    </section>

    <section class="section team-preview">
        <div class="container">
            <div class="section-heading">
                <div><p class="eyebrow">{{ __('site.team_eyebrow') }}</p><h2>{{ __('site.team_title') }}</h2><p class="section-intro">{{ __('site.team_home_intro') }}</p></div>
                <a class="text-link" href="{{ $urls->route('dentists.index') }}">{{ __('site.meet_team') }} <span class="direction-arrow" aria-hidden="true">↗</span></a>
            </div>
            <div class="team-home-grid">
                @foreach ($dentists as $dentist)
                    <article class="team-home-card">
                        <div class="team-initials" aria-hidden="true" dir="ltr">{{ $dentist['initials'] }}</div>
                        <div>
                            <p class="sample-label">{{ __('site.sample_profile') }}</p>
                            <h3>{{ $dentist['name'] }}</h3>
                            <p class="team-role">{{ $dentist['role'] }}</p>
                            <p>{{ $dentist['bio'] }}</p>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="section section-tint">
        <div class="container practical-layout">
            <div class="practical-copy">
                <p class="eyebrow">{{ __('site.clinic_details') }}</p>
                <h2>{{ __('site.practical_title') }}</h2>
                <p class="lead">{{ __('site.practical_intro') }}</p>
                <div class="location-line">
                    <span class="location-mark" aria-hidden="true">⌖</span>
                    <address>{{ __('site.central_london') }}<br><span>{{ $clinic['address']['line_1'] }}, {{ $clinic['address']['city'] }} <bdi>{{ $clinic['address']['postcode'] }}</bdi></span></address>
                </div>
                <a class="text-link" href="{{ $urls->route('contact') }}">{{ __('site.view_contact') }} <span class="direction-arrow" aria-hidden="true">↗</span></a>
            </div>
            <div class="hours-panel">
                <p class="eyebrow">{{ __('site.hours') }}</p>
                @php($profile = $clinic)
                @include('partials.hours')
            </div>
        </div>
    </section>

    <section class="section container">
        <div class="final-cta">
            <div>
                <p class="eyebrow">{{ __('site.final_eyebrow') }}</p>
                <h2>{{ __('site.final_title') }}</h2>
                <p class="lead">{{ __('site.final_text') }}</p>
            </div>
            <div class="final-action">
                <p>{{ __('site.final_support') }}</p>
                <a class="button button-light" href="{{ $urls->route('booking.demo') }}">{{ __('site.book_cta') }} <span class="direction-arrow" aria-hidden="true">↗</span></a>
            </div>
        </div>
    </section>
</x-layout>

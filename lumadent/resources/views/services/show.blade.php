@inject('urls', 'App\Support\LocalizedUrl')
<x-layout :title="$service['name']" :description="$service['summary']">
    @if (isset($service['suitability'], $service['journey'], $service['faqs']))
        <section class="container treatment-hero">
            <div class="treatment-hero-copy">
                <a class="text-link" href="{{ $urls->route('services.index') }}">{{ __('site.back_services') }}</a>
                <p class="eyebrow">{{ __('site.services') }}</p>
                <h1>{{ $service['name'] }}</h1>
                <p class="lead">{{ $service['summary'] }}</p>
                <p class="treatment-intro">{{ $service['description'] }}</p>
                <div class="actions">
                    <a class="button" href="{{ $urls->route('booking.demo') }}">{{ __('site.book_cta') }} <span class="direction-arrow" aria-hidden="true">↗</span></a>
                </div>
            </div>
            <aside class="treatment-note">
                <span class="panel-symbol" aria-hidden="true">✦</span>
                <p>{{ __('site.service_note') }}</p>
            </aside>
        </section>

        <section class="section section-tint">
            <div class="container service-suitability">
                <p class="eyebrow">{{ __('site.concept') }}</p>
                <h2>{{ __('site.service_suitability') }}</h2>
                <p class="lead">{{ $service['suitability'] }}</p>
            </div>
        </section>

        <section class="container section treatment-journey">
            <div class="treatment-section-heading">
                <p class="eyebrow">{{ __('site.services') }}</p>
                <h2>{{ __('site.service_journey') }}</h2>
                <p class="section-intro">{{ __('site.service_journey_intro') }}</p>
            </div>
            <ol class="treatment-steps">
                @foreach ($service['journey'] as $step)
                    <li>
                        <span class="treatment-step-number" aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                        <div><h3>{{ $step['title'] }}</h3><p>{{ $step['text'] }}</p></div>
                    </li>
                @endforeach
            </ol>
        </section>

        <section class="section section-tint">
            <div class="container treatment-info-grid">
                @foreach (['alternatives' => 'service_alternatives', 'aftercare' => 'service_aftercare', 'risks' => 'service_risks'] as $field => $heading)
                    @isset($service[$field])
                        <article class="treatment-info-card">
                            <h2>{{ __('site.'.$heading) }}</h2>
                            <ul>
                                @foreach ($service[$field] as $item)<li>{{ $item }}</li>@endforeach
                            </ul>
                        </article>
                    @endisset
                @endforeach
            </div>
        </section>

        <section class="container section treatment-faqs">
            <div class="treatment-section-heading">
                <p class="eyebrow">FAQ</p>
                <h2>{{ __('site.service_faqs') }}</h2>
            </div>
            <div class="faq-list">
                @foreach ($service['faqs'] as $faq)
                    <details class="faq-item">
                        <summary>{{ $faq['question'] }}</summary>
                        <p>{{ $faq['answer'] }}</p>
                    </details>
                @endforeach
            </div>
        </section>

        <section class="container section">
            <div class="final-cta">
                <div><p class="eyebrow">{{ __('site.concept') }}</p><h2>{{ __('site.service_final_title') }}</h2></div>
                <div class="final-action">
                    <p>{{ __('site.service_final_text') }}</p>
                    <a class="button button-light" href="{{ $urls->route('booking.demo') }}">{{ __('site.book_cta') }} <span class="direction-arrow" aria-hidden="true">↗</span></a>
                </div>
            </div>
        </section>
    @else
        <section class="container section reading-width">
            <a class="text-link" href="{{ $urls->route('services.index') }}">{{ __('site.back_services') }}</a>
            <div class="page-heading"><p class="eyebrow">{{ __('site.services') }}</p><h1>{{ $service['name'] }}</h1><p class="lead">{{ $service['summary'] }}</p></div>
            <div class="prose"><p>{{ $service['description'] }}</p><p class="notice">{{ __('site.service_note') }}</p></div>
            <a class="button" href="{{ $urls->route('booking.demo') }}">{{ __('site.book_cta') }} <span class="direction-arrow" aria-hidden="true">↗</span></a>
        </section>
    @endif
</x-layout>

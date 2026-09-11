@props(['title', 'description' => null, 'noindex' => false])
@inject('urls', 'App\Support\LocalizedUrl')
@php
    $locale = app()->getLocale();
    $languages = config('localization.locales');
    $profile = app(\App\Content\ClinicProfile::class)->get();
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $languages[$locale]['direction'] ?? 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <script>{!! file_get_contents(resource_path('js/theme.js')) !!}</script>
    <title>{{ $title }} | {{ $profile['name'] }}</title>
    <meta name="description" content="{{ $description ?? __('site.description') }}">
    @if ($noindex)
        <meta name="robots" content="noindex, follow">
    @elseif ($urls->pageName())
        <link rel="canonical" href="{{ $urls->current() }}">
        @foreach ($languages as $code => $language)
            <link rel="alternate" hreflang="{{ $code }}" href="{{ $urls->current($code) }}">
        @endforeach
        <link rel="alternate" hreflang="x-default" href="{{ $urls->current(config('localization.default')) }}">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <a class="skip-link" href="#main">{{ __('site.skip') }}</a>
    <div class="concept-bar">
        <div class="container concept-inner">
            <span class="concept-label" lang="en" dir="ltr">Concept Project</span>
            <span>{{ __('site.concept_short') }}</span>
        </div>
    </div>
    <header class="site-header">
        <div class="container header-inner">
            <a class="brand" href="{{ $urls->route('home') }}" aria-label="{{ $profile['name'] }} — {{ __('site.home') }}">
                <span class="brand-symbol" aria-hidden="true">L<span>✦</span></span>
                <span dir="ltr">{{ $profile['name'] }}</span>
            </a>
            <nav class="desktop-nav" aria-label="{{ __('site.navigation') }}">
                @include('partials.navigation')
            </nav>
            <div class="header-tools">
                <nav class="language-switch" aria-label="{{ __('site.language') }}">
                    @foreach ($languages as $code => $language)
                        <a href="{{ $noindex ? $urls->route('home', [], $code) : $urls->current($code) }}" lang="{{ $code }}" dir="{{ $language['direction'] }}" hreflang="{{ $code }}" @if ($code === $locale) aria-current="true" @endif>{{ $language['label'] }}</a>
                    @endforeach
                </nav>
                <label class="theme-control" data-theme-control hidden>
                    <span class="sr-only">{{ __('site.theme') }}</span>
                    <select data-theme-select aria-label="{{ __('site.theme') }}">
                        <option value="auto">{{ __('site.auto') }}</option>
                        <option value="light">{{ __('site.light') }}</option>
                        <option value="dark">{{ __('site.dark') }}</option>
                    </select>
                </label>
            </div>
            <details class="mobile-menu">
                <summary>{{ __('site.menu') }} <span aria-hidden="true">☰</span></summary>
                <nav aria-label="{{ __('site.navigation') }}">
                    @include('partials.navigation')
                </nav>
            </details>
        </div>
    </header>
    <main id="main" tabindex="-1">{{ $slot }}</main>
    <footer class="site-footer">
        <div class="container footer-main">
            <div>
                <a class="brand" href="{{ $urls->route('home') }}" dir="ltr">{{ $profile['name'] }}</a>
                <p>{{ __('site.footer_note') }}</p>
            </div>
            <nav class="footer-links" aria-label="{{ __('site.privacy') }}">
                <a href="{{ $urls->route('contact') }}">{{ __('site.contact') }}</a>
                <a href="{{ $urls->route('privacy') }}">{{ __('site.privacy') }}</a>
                <a href="{{ $urls->route('booking.demo') }}">{{ __('site.book') }}</a>
            </nav>
        </div>
        <div class="container footer-notice">
            <p><span lang="en" dir="ltr">Concept Project</span> · {{ $profile['concept_notice'] }}</p>
            <p class="footer-credit">
                <span>{{ __('site.footer_credit_prefix') }}</span>
                <span lang="en" dir="ltr">ProDay Web Studio.</span>
                <a href="{{ config('project.repository_url') }}" target="_blank" rel="noopener noreferrer">
                    {{ __('site.footer_source') }} <span class="direction-arrow" aria-hidden="true">↗</span><span class="sr-only"> ({{ __('site.opens_new_tab') }})</span>
                </a>
            </p>
        </div>
    </footer>
</body>
</html>

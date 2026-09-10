@php($profile = app(\App\Content\ClinicProfile::class)->get())
<x-layout :title="__('site.contact')" :description="__('site.contact_text')">
    <section class="container section">
        <div class="page-heading"><p class="eyebrow">{{ __('site.contact') }}</p><h1>{{ __('site.contact_intro') }}</h1><p class="lead">{{ __('site.contact_text') }}</p></div>
        <div class="contact-grid">
            <div class="service-card contact-details"><h2>{{ __('site.address') }}</h2><address>{{ $profile['address']['line_1'] }}<br>{{ $profile['address']['city'] }} <bdi>{{ $profile['address']['postcode'] }}</bdi><br>{{ $profile['address']['country'] }}</address><h3>{{ __('site.phone') }}</h3><p><bdi>{{ $profile['phone'] }}</bdi></p><h3>{{ __('site.email') }}</h3><p><bdi>{{ $profile['email'] }}</bdi></p></div>
            <div class="service-card"><h2>{{ __('site.hours') }}</h2>@include('partials.hours')</div>
        </div>
    </section>
</x-layout>

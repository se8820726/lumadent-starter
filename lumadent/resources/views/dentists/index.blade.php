<x-layout :title="__('site.dentists')" :description="__('site.team_intro')">
    <section class="container section">
        <div class="page-heading"><p class="eyebrow">{{ __('site.dentists') }}</p><h1>{{ __('site.step_two') }}</h1><p class="lead">{{ __('site.team_intro') }}</p></div>
        <div class="team-grid">
            @foreach ($dentists as $dentist)
                <article class="service-card"><p class="eyebrow">{{ __('site.sample_profile') }}</p><h2>{{ $dentist['name'] }}</h2><p class="team-role">{{ $dentist['role'] }}</p><p>{{ $dentist['bio'] }}</p></article>
            @endforeach
        </div>
    </section>
</x-layout>

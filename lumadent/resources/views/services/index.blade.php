<x-layout :title="__('site.services')" :description="__('site.services_intro')">
    <section class="container section">
        <div class="page-heading"><p class="eyebrow">{{ __('site.services') }}</p><h1>{{ __('site.featured_services') }}</h1><p class="lead">{{ __('site.services_intro') }}</p></div>
        <div class="card-grid">@foreach ($services as $service)<x-service-card :service="$service" />@endforeach</div>
    </section>
</x-layout>

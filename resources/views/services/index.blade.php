<h1>Services</h1>
@foreach ($services as $service)
    <a href="{{ route('services.show', $service['slug']) }}">{{ $service['name'] }}</a>
@endforeach

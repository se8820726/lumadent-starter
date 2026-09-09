<h1>Dentists</h1>
@foreach ($dentists as $dentist)
    <article>
        <h2>{{ $dentist['name'] }}</h2>
        <p>Sample profile</p>
    </article>
@endforeach

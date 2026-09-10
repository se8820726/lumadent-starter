<?php

namespace App\Content;

final class ServiceCatalog
{
    use TranslatesContent;

    public function all(): array
    {
        return array_values(array_map(
            fn (array $service): array => $this->translated('services.'.$service['slug'], $service),
            config('treatments', []),
        ));
    }

    public function featured(): array
    {
        return array_values(array_filter(
            $this->all(),
            fn (array $service): bool => $service['featured'],
        ));
    }

    public function findOrFail(string $slug): array
    {
        $service = collect($this->all())->firstWhere('slug', $slug);

        abort_if($service === null, 404);

        return $service;
    }
}

<?php

namespace App\Content;

final class DentistDirectory
{
    use TranslatesContent;

    public function all(): array
    {
        return array_values(array_map(
            fn (array $dentist, int $index): array => $this->translated('dentists.'.$index, $dentist),
            config('dentists', []), array_keys(config('dentists', [])),
        ));
    }

    public function featured(): array
    {
        return array_values(array_filter(
            $this->all(),
            fn (array $dentist): bool => $dentist['featured'],
        ));
    }
}

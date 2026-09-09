<?php

namespace App\Content;

final class DentistDirectory
{
    public function all(): array
    {
        return array_values(config('dentists', []));
    }

    public function featured(): array
    {
        return array_values(array_filter(
            $this->all(),
            fn (array $dentist): bool => $dentist['featured'],
        ));
    }
}

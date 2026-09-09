<?php

namespace App\Content;

final class ClinicProfile
{
    public function get(): array
    {
        return config('clinic');
    }
}

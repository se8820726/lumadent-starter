<?php

namespace App\Content;

final class ClinicProfile
{
    use TranslatesContent;

    public function get(): array
    {
        return $this->translated('clinic', config('clinic'));
    }
}

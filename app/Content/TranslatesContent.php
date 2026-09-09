<?php

namespace App\Content;

trait TranslatesContent
{
    private function translated(string $key, array $original): array
    {
        $translation = trans('content.'.$key, [], app()->getLocale());

        return is_array($translation) ? array_replace_recursive($original, $translation) : $original;
    }
}

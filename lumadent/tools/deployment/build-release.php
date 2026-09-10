<?php

declare(strict_types=1);

use Lumadent\Deployment\ReleaseBuilder;

require dirname(__DIR__, 2).'/vendor/autoload.php';
require_once __DIR__.'/ReleaseBuilder.php';

$options = getopt('', ['source:', 'output:', 'release-id:', 'run-id:', 'built-at:']);

try {
    foreach (['source', 'output', 'release-id', 'run-id', 'built-at'] as $required) {
        if (! isset($options[$required]) || ! is_string($options[$required]) || $options[$required] === '') {
            throw new InvalidArgumentException("Missing required option: --{$required}");
        }
    }

    $builder = new ReleaseBuilder;
    $builder->build($options['source'], $options['output'], [
        'release_id' => $options['release-id'],
        'run_id' => $options['run-id'],
        'built_at' => $options['built-at'],
    ]);
    $builder->createArchive($options['output']);

    fwrite(STDOUT, "Release package created.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'Release packaging failed: '.preg_replace('/[\r\n]+/', ' ', $exception->getMessage()).PHP_EOL);
    exit(1);
}

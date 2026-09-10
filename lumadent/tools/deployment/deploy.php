<?php

declare(strict_types=1);

use Lumadent\Deployment\DeployClient;

require dirname(__DIR__, 2).'/vendor/autoload.php';
require_once __DIR__.'/DeployClient.php';

try {
    $required = ['DEPLOY_ENDPOINT', 'DEPLOY_SECRET', 'ARTIFACT_URL', 'RELEASE_SHA256'];
    $environment = [];
    foreach ($required as $name) {
        $value = getenv($name);
        if (! is_string($value) || $value === '') {
            throw new RuntimeException("Missing required environment setting: {$name}");
        }
        $environment[$name] = $value;
    }

    $timeout = (int) (getenv('DEPLOY_TIMEOUT_SECONDS') ?: 600);
    $client = new DeployClient($environment['DEPLOY_ENDPOINT'], $environment['DEPLOY_SECRET'], $timeout);
    $client->run(['artifact_url' => $environment['ARTIFACT_URL'], 'sha256' => $environment['RELEASE_SHA256']]);

    fwrite(STDOUT, "Production deployment succeeded.\n");
} catch (Throwable $exception) {
    $message = preg_replace('/https:\/\/\S+/i', '[redacted-url]', preg_replace('/[\r\n]+/', ' ', $exception->getMessage()));
    fwrite(STDERR, 'Production deployment failed: '.$message.PHP_EOL);
    exit(1);
}

<?php

declare(strict_types=1);

use Lumadent\Deployment\DeployClient;

require dirname(__DIR__, 2).'/vendor/autoload.php';
require_once __DIR__.'/DeployClient.php';

try {
    $required = ['DEPLOY_ENDPOINT', 'DEPLOY_SECRET', 'ARTIFACT_URL', 'RELEASE_SHA256', 'GITHUB_SHA', 'GITHUB_RUN_ID', 'GITHUB_RUN_ATTEMPT', 'HEALTH_URLS'];
    $environment = [];
    foreach ($required as $name) {
        $value = getenv($name);
        if (! is_string($value) || $value === '') {
            throw new RuntimeException("Missing required environment setting: {$name}");
        }
        $environment[$name] = $value;
    }
    $timeout = (int) (getenv('DEPLOY_TIMEOUT_SECONDS') ?: 600);
    $poll = (int) (getenv('DEPLOY_POLL_SECONDS') ?: 2);
    $healthTargets = json_decode($environment['HEALTH_URLS'], true, 16, JSON_THROW_ON_ERROR);
    if (! is_array($healthTargets)) {
        throw new RuntimeException('HEALTH_URLS is invalid.');
    }
    $client = new DeployClient($environment['DEPLOY_ENDPOINT'], $environment['DEPLOY_SECRET'], $timeout, $poll);
    $client->run([
        'deployment_id' => $environment['GITHUB_RUN_ID'].'-'.$environment['GITHUB_RUN_ATTEMPT'],
        'run_id' => $environment['GITHUB_RUN_ID'],
        'run_attempt' => (int) $environment['GITHUB_RUN_ATTEMPT'],
        'release_id' => strtolower($environment['GITHUB_SHA']),
        'sha256' => strtolower($environment['RELEASE_SHA256']),
        'artifact_url' => $environment['ARTIFACT_URL'],
    ], array_values($healthTargets));
    fwrite(STDOUT, "Production deployment and public checks succeeded.\n");
} catch (Throwable $exception) {
    $message = preg_replace('/https:\/\/\S+/i', '[redacted-url]', preg_replace('/[\r\n]+/', ' ', $exception->getMessage()));
    fwrite(STDERR, 'Production deployment failed: '.$message.PHP_EOL);
    exit(1);
}

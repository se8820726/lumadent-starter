<?php

namespace Tests\Unit\Deployment;

require_once __DIR__.'/../../../tools/deployment/DeployClient.php';

use Lumadent\Deployment\DeployClient;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DeployClientTest extends TestCase
{
    public function test_production_workflow_has_safe_triggers_concurrency_and_permissions(): void
    {
        $workflow = file_get_contents(__DIR__.'/../../../../.github/workflows/deploy.yml');

        self::assertIsString($workflow);
        self::assertStringContainsString('branches: [main]', $workflow);
        self::assertStringContainsString('workflow_dispatch:', $workflow);
        self::assertStringContainsString('group: lumadent-production', $workflow);
        self::assertStringContainsString('cancel-in-progress: true', $workflow);
        self::assertStringContainsString('environment: production', $workflow);
        self::assertStringContainsString('contents: read', $workflow);
        self::assertStringContainsString('actions: read', $workflow);
        self::assertStringNotContainsString('pull_request:', $workflow);
        self::assertStringNotContainsString("'rollback'", $workflow);
    }

    public function test_it_signs_requests_with_the_documented_canonical_format(): void
    {
        $captured = null;
        $transport = static function (string $url, string $body, array $headers) use (&$captured): array {
            $captured = compact('url', 'body', 'headers');

            return ['status' => 200, 'body' => '{"deployment":{"status":"succeeded"}}', 'headers' => []];
        };
        $client = new DeployClient('https://deploy.example.test/hook', str_repeat('s', 32), 60, 1, $transport, null, static fn (): int => 1725962400, static fn (): int => 0);
        $client->request('status', ['deployment_id' => '100-1']);
        $nonce = $captured['headers']['X-Deploy-Nonce'];
        $expected = hash_hmac('sha256', "POST\nstatus\n1725962400\n{$nonce}\n{$captured['body']}", str_repeat('s', 32));
        self::assertSame($expected, $captured['headers']['X-Deploy-Signature']);
    }

    public function test_external_failure_does_not_send_a_rollback_request(): void
    {
        $actions = [];
        $transport = static function (string $url, string $body) use (&$actions): array {
            $payload = json_decode($body, true);
            $actions[] = $payload['action'];

            return ['status' => 200, 'body' => '{"deployment":{"status":"succeeded"}}', 'headers' => []];
        };
        $health = static fn (): array => ['status' => 500, 'headers' => [], 'body' => 'broken'];
        $client = new DeployClient('https://deploy.example.test/hook', str_repeat('s', 32), 60, 1, $transport, $health, static fn (): int => 1725962400, static fn (): int => 0);

        try {
            $client->run(['deployment_id' => '100-1', 'release_id' => str_repeat('a', 40)], [['url' => 'https://example.test/', 'status' => [200], 'marker' => 'Concept Project']]);
            self::fail('External health verification should fail.');
        } catch (RuntimeException $exception) {
            self::assertSame('External public health verification failed.', $exception->getMessage());
        }
        self::assertSame(['start'], $actions);
    }

    public function test_it_polls_until_the_deployer_succeeds(): void
    {
        $statuses = ['downloaded', 'verified', 'succeeded'];
        $transport = static function () use (&$statuses): array {
            $status = array_shift($statuses);

            return ['status' => $status === 'succeeded' ? 200 : 202, 'body' => json_encode(['deployment' => ['status' => $status]]), 'headers' => []];
        };
        $health = static fn (): array => ['status' => 200, 'headers' => ['x-lumadent-release' => str_repeat('a', 40)], 'body' => 'Concept Project'];
        $time = 1000;
        $client = new DeployClient('https://deploy.example.test/hook', str_repeat('s', 32), 60, 1, $transport, $health, static function () use (&$time): int {
            return $time++;
        }, static fn (): int => 0);
        $result = $client->run(['deployment_id' => '100-1', 'release_id' => str_repeat('a', 40)], [['url' => 'https://example.test/', 'status' => [200], 'marker' => 'Concept Project']]);
        self::assertSame('succeeded', $result['deployment']['status']);
    }
}

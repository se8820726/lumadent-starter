<?php

namespace Tests\Unit\Deployment;

require_once __DIR__.'/../../../tools/deployment/DeployClient.php';

use Lumadent\Deployment\DeployClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DeployClientTest extends TestCase
{
    public function test_production_workflow_has_safe_triggers_concurrency_permissions_and_synchronous_contract(): void
    {
        $workflow = file_get_contents(__DIR__.'/../../../../.github/workflows/deploy.yml');
        self::assertIsString($workflow);
        foreach (['branches: [main]', 'workflow_dispatch:', 'group: lumadent-production', 'cancel-in-progress: true', 'environment: production', 'contents: read', 'actions: read', 'DEPLOY_ENDPOINT: ${{ secrets.DEPLOY_ENDPOINT }}', 'DEPLOY_SECRET: ${{ secrets.DEPLOY_SECRET }}', 'run: php tools/deployment/deploy.php'] as $expected) {
            self::assertStringContainsString($expected, $workflow);
        }
        self::assertStringNotContainsString('pull_request:', $workflow);
        self::assertStringNotContainsString('DEPLOY_POLL_SECONDS', $workflow);
    }

    public function test_it_sends_one_request_with_the_approved_signature(): void
    {
        $captured = null;
        $transport = static function (string $url, string $body, array $headers) use (&$captured): array {
            $captured = compact('url', 'body', 'headers');

            return ['status' => 200, 'body' => '{"deployment":{"status":"succeeded"}}'];
        };
        $client = new DeployClient('https://deploy.example.test/deploy.php', str_repeat('s', 32), 600, $transport, static fn (): string => str_repeat('ab', 16));
        $result = $client->run(['artifact_url' => 'https://artifact.example.test/release', 'sha256' => str_repeat('C', 64)]);
        $payload = json_decode($captured['body'], true, 16, JSON_THROW_ON_ERROR);
        $canonical = $payload['artifact_url']."\n".$payload['sha256']."\n".$payload['salt'];

        self::assertSame('https://deploy.example.test/deploy.php', $captured['url']);
        self::assertSame(['Content-Type' => 'application/json'], $captured['headers']);
        self::assertSame(str_repeat('c', 64), $payload['sha256']);
        self::assertSame(hash_hmac('sha256', $canonical, str_repeat('s', 32)), $payload['signature']);
        self::assertSame('succeeded', $result['deployment']['status']);
    }

    #[DataProvider('failureResponses')]
    public function test_it_rejects_every_non_success_response(int $httpStatus, string $body, string $expectedStatus): void
    {
        $client = new DeployClient('https://deploy.example.test/deploy.php', str_repeat('s', 32), 600, static fn (): array => ['status' => $httpStatus, 'body' => $body], static fn (): string => str_repeat('ab', 16));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($expectedStatus);
        $client->run(['artifact_url' => 'https://artifact.example.test/private-token', 'sha256' => str_repeat('c', 64)]);
    }

    /** @return array<string,array{int,string,string}> */
    public static function failureResponses(): array
    {
        return [
            'rolled back' => [422, '{"deployment":{"status":"rolled_back"}}', 'rolled_back'],
            'rollback failed' => [500, '{"deployment":{"status":"rollback_failed"}}', 'rollback_failed'],
            'busy' => [409, '{"deployment":{"status":"busy"}}', 'busy'],
            'malformed body' => [200, 'not-json', 'invalid response'],
            'wrong success code' => [202, '{"deployment":{"status":"succeeded"}}', 'succeeded'],
        ];
    }

    public function test_it_rejects_invalid_metadata_without_exposing_the_artifact_url(): void
    {
        $client = new DeployClient('https://deploy.example.test/deploy.php', str_repeat('s', 32), 600, static fn (): array => []);
        try {
            $client->run(['artifact_url' => 'http://artifact.example.test/private-token', 'sha256' => str_repeat('c', 64)]);
            self::fail('Invalid metadata should fail.');
        } catch (RuntimeException $exception) {
            self::assertStringNotContainsString('private-token', $exception->getMessage());
        }
    }
}

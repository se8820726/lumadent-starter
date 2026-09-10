<?php

namespace Lumadent\Deployment;

use Closure;
use RuntimeException;

final class DeployClient
{
    private Closure $transport;

    private Closure $healthTransport;

    private Closure $clock;

    private Closure $sleep;

    public function __construct(
        private readonly string $endpoint,
        private readonly string $secret,
        private readonly int $timeoutSeconds = 600,
        private readonly int $pollSeconds = 2,
        ?callable $transport = null,
        ?callable $healthTransport = null,
        ?callable $clock = null,
        ?callable $sleep = null,
    ) {
        $parts = parse_url($endpoint);
        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https' || ! isset($parts['host'])) {
            throw new RuntimeException('DEPLOY_ENDPOINT must be an HTTPS URL.');
        }
        if (strlen($secret) < 32) {
            throw new RuntimeException('DEPLOY_SECRET must contain at least 32 bytes.');
        }
        if ($timeoutSeconds < 30 || $timeoutSeconds > 3600 || $pollSeconds < 1 || $pollSeconds > 30) {
            throw new RuntimeException('Deployment timing configuration is invalid.');
        }
        $this->transport = Closure::fromCallable($transport ?? $this->post(...));
        $this->healthTransport = Closure::fromCallable($healthTransport ?? $this->get(...));
        $this->clock = Closure::fromCallable($clock ?? time(...));
        $this->sleep = Closure::fromCallable($sleep ?? sleep(...));
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function request(string $action, array $payload): array
    {
        $body = json_encode(array_merge(['action' => $action], $payload), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $timestamp = (string) ($this->clock)();
        $nonce = $this->uuid();
        $signature = hash_hmac('sha256', "POST\n{$action}\n{$timestamp}\n{$nonce}\n{$body}", $this->secret);
        $response = ($this->transport)($this->endpoint, $body, [
            'Content-Type' => 'application/json',
            'X-Deploy-Timestamp' => $timestamp,
            'X-Deploy-Nonce' => $nonce,
            'X-Deploy-Signature' => $signature,
        ]);
        if (! is_array($response) || ! is_int($response['status'] ?? null) || ! is_string($response['body'] ?? null)) {
            throw new RuntimeException('The deployer returned an invalid response.');
        }
        $data = json_decode($response['body'], true, 16, JSON_THROW_ON_ERROR);
        if (! is_array($data) || ! in_array($response['status'], [200, 202, 409], true)) {
            throw new RuntimeException('The deployer rejected the request.');
        }

        return $data;
    }

    /** @param array<string,mixed> $deployment @param list<array{url:string,status:list<int>,marker:string}> $healthTargets @return array<string,mixed> */
    public function run(array $deployment, array $healthTargets): array
    {
        $id = $deployment['deployment_id'] ?? null;
        if (! is_string($id) || preg_match('/\A[0-9]+-[0-9]+\z/', $id) !== 1) {
            throw new RuntimeException('Deployment metadata is invalid.');
        }
        $deadline = ($this->clock)() + $this->timeoutSeconds;
        $result = $this->request('start', $deployment);

        while (! $this->isTerminal($result)) {
            if (($this->clock)() >= $deadline) {
                try {
                    $this->request('status', ['deployment_id' => $id]);
                } catch (\Throwable) {
                }
                throw new RuntimeException('Deployment polling timed out.');
            }
            ($this->sleep)($this->pollSeconds);
            $result = $this->request('progress', ['deployment_id' => $id]);
        }

        $status = $result['deployment']['status'] ?? null;
        if ($status !== 'succeeded') {
            throw new RuntimeException('Deployment failed with status: '.(is_string($status) ? $status : 'unknown'));
        }
        $releaseId = $deployment['release_id'] ?? null;
        if (! is_string($releaseId)) {
            throw new RuntimeException('Deployment metadata is invalid.');
        }
        $this->verifyPublicUrls($healthTargets, $releaseId);

        return $result;
    }

    /** @param list<array{url:string,status:list<int>,marker:string}> $targets */
    public function verifyPublicUrls(array $targets, string $releaseId): void
    {
        if ($targets === []) {
            throw new RuntimeException('At least one public health URL is required.');
        }
        foreach ($targets as $target) {
            if (! is_string($target['url'] ?? null) || ! str_starts_with($target['url'], 'https://') || ! is_array($target['status'] ?? null) || ! is_string($target['marker'] ?? null)) {
                throw new RuntimeException('Public health configuration is invalid.');
            }
            $response = ($this->healthTransport)($target['url']);
            if (! is_array($response) || ! in_array($response['status'] ?? null, $target['status'], true)
                || ! is_string($response['body'] ?? null) || ! str_contains($response['body'], $target['marker'])
                || strtolower((string) (($response['headers']['x-lumadent-release'] ?? $response['headers']['X-LumaDent-Release'] ?? ''))) !== $releaseId) {
                throw new RuntimeException('External public health verification failed.');
            }
        }
    }

    /** @return array{status:int,body:string,headers:array<string,string>} */
    private function post(string $url, string $body, array $headers): array
    {
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name.': '.$value;
        }
        $context = stream_context_create([
            'http' => ['method' => 'POST', 'timeout' => 60, 'ignore_errors' => true, 'follow_location' => 0, 'header' => implode("\r\n", $headerLines), 'content' => $body],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $responseBody = @file_get_contents($url, false, $context);
        if (! is_string($responseBody)) {
            throw new RuntimeException('Unable to contact the deployer.');
        }
        $rawHeaders = $http_response_header ?? [];

        return ['status' => $this->status($rawHeaders), 'body' => $responseBody, 'headers' => $this->headers($rawHeaders)];
    }

    /** @return array{status:int,body:string,headers:array<string,string>} */
    private function get(string $url): array
    {
        $context = stream_context_create([
            'http' => ['method' => 'GET', 'timeout' => 30, 'ignore_errors' => true, 'follow_location' => 1, 'max_redirects' => 5, 'header' => "User-Agent: LumaDent-Deployment-Check/1\r\n"],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $body = @file_get_contents($url, false, $context);
        if (! is_string($body) || strlen($body) > 2097152) {
            throw new RuntimeException('External public health verification failed.');
        }
        $rawHeaders = $http_response_header ?? [];

        return ['status' => $this->status($rawHeaders), 'body' => $body, 'headers' => $this->headers($rawHeaders)];
    }

    private function isTerminal(array $response): bool
    {
        return in_array($response['deployment']['status'] ?? null, ['succeeded', 'failed', 'rolled_back', 'rollback_failed'], true);
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);
        $hex = bin2hex($bytes);

        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4).'-'.substr($hex, 16, 4).'-'.substr($hex, 20);
    }

    private function status(array $headers): int
    {
        foreach (array_reverse($headers) as $header) {
            if (is_string($header) && preg_match('/\AHTTP\/\S+\s+([0-9]{3})\b/i', $header, $matches) === 1) {
                return (int) $matches[1];
            }
        }
        throw new RuntimeException('HTTP status is missing.');
    }

    /** @return array<string,string> */
    private function headers(array $headers): array
    {
        $result = [];
        foreach ($headers as $header) {
            if (is_string($header) && str_contains($header, ':')) {
                [$name, $value] = explode(':', $header, 2);
                $result[strtolower(trim($name))] = trim($value);
            }
        }

        return $result;
    }
}

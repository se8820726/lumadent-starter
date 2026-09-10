<?php

namespace Lumadent\Deployment;

use Closure;
use JsonException;
use RuntimeException;

final class DeployClient
{
    private Closure $transport;

    private Closure $saltFactory;

    public function __construct(
        private readonly string $endpoint,
        private readonly string $secret,
        private readonly int $timeoutSeconds = 600,
        ?callable $transport = null,
        ?callable $saltFactory = null,
    ) {
        $parts = parse_url($endpoint);
        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https' || ! isset($parts['host'])) {
            throw new RuntimeException('DEPLOY_ENDPOINT must be an HTTPS URL.');
        }
        if (strlen($secret) < 32) {
            throw new RuntimeException('DEPLOY_SECRET must contain at least 32 bytes.');
        }
        if ($timeoutSeconds < 30 || $timeoutSeconds > 3600) {
            throw new RuntimeException('Deployment timeout configuration is invalid.');
        }

        $this->transport = Closure::fromCallable($transport ?? $this->post(...));
        $this->saltFactory = Closure::fromCallable($saltFactory ?? static fn (): string => bin2hex(random_bytes(16)));
    }

    /** @param array{artifact_url:mixed,sha256:mixed} $deployment @return array<string,mixed> */
    public function run(array $deployment): array
    {
        $artifactUrl = $deployment['artifact_url'] ?? null;
        $sha256 = $deployment['sha256'] ?? null;
        $parts = is_string($artifactUrl) ? parse_url($artifactUrl) : false;

        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https' || ! isset($parts['host'])
            || ! is_string($sha256) || preg_match('/\A[a-fA-F0-9]{64}\z/', $sha256) !== 1) {
            throw new RuntimeException('Deployment metadata is invalid.');
        }

        $sha256 = strtolower($sha256);
        $salt = ($this->saltFactory)();
        if (! is_string($salt) || preg_match('/\A[a-f0-9]{32,128}\z/', $salt) !== 1) {
            throw new RuntimeException('Unable to create deployment salt.');
        }

        $canonical = $artifactUrl."\n".$sha256."\n".$salt;
        $body = json_encode([
            'artifact_url' => $artifactUrl,
            'sha256' => $sha256,
            'salt' => $salt,
            'signature' => hash_hmac('sha256', $canonical, $this->secret),
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $response = ($this->transport)($this->endpoint, $body, ['Content-Type' => 'application/json']);
        if (! is_array($response) || ! is_int($response['status'] ?? null) || ! is_string($response['body'] ?? null)) {
            throw new RuntimeException('The deployer returned an invalid response.');
        }

        try {
            $data = json_decode($response['body'], true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('The deployer returned an invalid response.');
        }

        if (! is_array($data) || $response['status'] !== 200 || ($data['deployment']['status'] ?? null) !== 'succeeded') {
            $status = $data['deployment']['status'] ?? 'unknown';
            throw new RuntimeException('Deployment failed with status: '.(is_string($status) ? $status : 'unknown'));
        }

        return $data;
    }

    /** @return array{status:int,body:string} */
    private function post(string $url, string $body, array $headers): array
    {
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name.': '.$value;
        }
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'timeout' => $this->timeoutSeconds,
                'ignore_errors' => true,
                'follow_location' => 0,
                'header' => implode("\r\n", $headerLines),
                'content' => $body,
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $responseBody = @file_get_contents($url, false, $context);
        if (! is_string($responseBody)) {
            throw new RuntimeException('Unable to contact the deployer.');
        }

        return ['status' => $this->status($http_response_header ?? []), 'body' => $responseBody];
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
}

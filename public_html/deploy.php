<?php

declare(strict_types=1);

use function Lumadent\Deployer\runDeployment;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo '{"deployment":{"status":"method_not_allowed"}}';
    exit;
}

try {
    $stream = fopen(PHP_SAPI === 'cli' ? 'php://stdin' : 'php://input', 'rb');
    if (! is_resource($stream)) {
        throw new RuntimeException('invalid_request');
    }
    $raw = stream_get_contents($stream, 65537);
    fclose($stream);
    if (! is_string($raw) || strlen($raw) > 65536) {
        throw new RuntimeException('invalid_request');
    }
    $request = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
    if (! is_array($request)) {
        throw new RuntimeException('invalid_request');
    }

    require_once dirname(__DIR__).'/deployer/deploy.php';
    $result = runDeployment($request);
    http_response_code($result['status']);
    echo json_encode($result['body'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
} catch (Throwable) {
    http_response_code(400);
    echo '{"deployment":{"status":"invalid_request"}}';
}

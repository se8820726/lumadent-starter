<?php

declare(strict_types=1);

namespace Lumadent\Deployer;

use Closure;
use FilesystemIterator;
use JsonException;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;
use ZipArchive;

final class SharedHostDeployer
{
    private const MAX_REDIRECTS = 5;

    private const MAX_HEALTH_BODY = 2097152;

    private readonly string $deployerDirectory;

    private readonly string $lumadentDirectory;

    private readonly string $publicDirectory;

    private readonly Closure $downloader;

    private readonly Closure $healthTransport;

    public function __construct(
        private readonly string $projectRoot,
        ?callable $downloader = null,
        ?callable $healthTransport = null,
    ) {
        $this->deployerDirectory = $projectRoot.DIRECTORY_SEPARATOR.'deployer';
        $this->lumadentDirectory = $projectRoot.DIRECTORY_SEPARATOR.'lumadent';
        $this->publicDirectory = $projectRoot.DIRECTORY_SEPARATOR.'public_html';
        $this->downloader = Closure::fromCallable($downloader ?? $this->download(...));
        $this->healthTransport = Closure::fromCallable($healthTransport ?? $this->healthGet(...));
    }

    /** @return array{status:int,body:array{deployment:array{status:string}}} */
    public function handle(array $request): array
    {
        try {
            [$artifactUrl, $sha256, $healthTargets] = $this->authenticate($request);
        } catch (UnauthorizedDeployment) {
            return $this->response(401, 'unauthorized');
        } catch (Throwable) {
            return $this->response(400, 'invalid_request');
        }

        if (! is_dir($this->deployerDirectory) || ! is_dir($this->lumadentDirectory) || ! is_dir($this->publicDirectory)) {
            return $this->response(500, 'failed');
        }

        $lock = @fopen($this->deployerDirectory.DIRECTORY_SEPARATOR.'deploy.lock', 'c+');
        if (! is_resource($lock)) {
            return $this->response(500, 'failed');
        }
        if (! flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);

            return $this->response(409, 'busy');
        }

        $artifactPath = $this->deployerDirectory.DIRECTORY_SEPARATOR.'artifact.zip';
        $releasePath = $this->deployerDirectory.DIRECTORY_SEPARATOR.'release.zip';
        $rollbackPath = $this->deployerDirectory.DIRECTORY_SEPARATOR.'rollback.zip';
        $rollbackRequired = false;
        $releaseEntries = [];

        try {
            $this->removeFile($artifactPath);
            $this->removeFile($releasePath);
            ($this->downloader)($artifactUrl, $artifactPath);
            $this->extractInnerRelease($artifactPath, $releasePath);
            $actualHash = hash_file('sha256', $releasePath);
            if (! is_string($actualHash) || ! hash_equals($sha256, strtolower($actualHash))) {
                throw new RuntimeException('release_checksum_mismatch');
            }

            $releaseEntries = $this->inspectRelease($releasePath);
            $this->createBackup($rollbackPath);
            $rollbackRequired = true;
            $this->extractRelease($releasePath, $releaseEntries);
            $this->runHealthChecks($healthTargets);

            return $this->response(200, 'succeeded');
        } catch (Throwable $deploymentFailure) {
            $this->log($deploymentFailure);
            if (! $rollbackRequired) {
                return $this->response(500, 'failed');
            }

            try {
                $this->removeReleaseTargets($releaseEntries);
                $this->restoreBackup($rollbackPath);

                return $this->response(422, 'rolled_back');
            } catch (Throwable $rollbackFailure) {
                $this->log($deploymentFailure, $rollbackFailure);

                return $this->response(500, 'rollback_failed');
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @return array{string,string,list<array{url:string,status:list<int>,marker:string}>} */
    private function authenticate(array $request): array
    {
        foreach (['artifact_url', 'sha256', 'salt', 'signature'] as $field) {
            if (! is_string($request[$field] ?? null)) {
                throw new RuntimeException('invalid_request');
            }
        }

        $parts = parse_url($request['artifact_url']);
        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https' || ! isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException('invalid_request');
        }
        if (preg_match('/\A[a-fA-F0-9]{64}\z/', $request['sha256']) !== 1
            || preg_match('/\A[a-f0-9]{32,128}\z/', $request['salt']) !== 1
            || preg_match('/\A[a-fA-F0-9]{64}\z/', $request['signature']) !== 1) {
            throw new RuntimeException('invalid_request');
        }

        $environment = $this->readEnvironment($this->lumadentDirectory.DIRECTORY_SEPARATOR.'.env');
        $secret = $environment['DEPLOY_SECRET'] ?? '';
        if (strlen($secret) < 32) {
            throw new RuntimeException('invalid_configuration');
        }

        $sha256 = strtolower($request['sha256']);
        $canonical = $request['artifact_url']."\n".$sha256."\n".$request['salt'];
        $expected = hash_hmac('sha256', $canonical, $secret);
        if (! hash_equals($expected, strtolower($request['signature']))) {
            throw new UnauthorizedDeployment('unauthorized');
        }

        try {
            $decoded = json_decode($environment['DEPLOY_HEALTH_URLS'] ?? '', true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('invalid_configuration');
        }
        if (! is_array($decoded) || $decoded === [] || ! array_is_list($decoded)) {
            throw new RuntimeException('invalid_configuration');
        }

        $healthTargets = [];
        foreach ($decoded as $target) {
            if (! is_array($target) || ! is_string($target['url'] ?? null) || ! is_array($target['status'] ?? null) || ! is_string($target['marker'] ?? null)) {
                throw new RuntimeException('invalid_configuration');
            }
            $targetParts = parse_url($target['url']);
            if (! is_array($targetParts) || ($targetParts['scheme'] ?? null) !== 'https' || ! isset($targetParts['host']) || $target['status'] === [] || ! array_is_list($target['status'])) {
                throw new RuntimeException('invalid_configuration');
            }
            $statuses = [];
            foreach ($target['status'] as $status) {
                if (! is_int($status) || $status < 100 || $status > 599) {
                    throw new RuntimeException('invalid_configuration');
                }
                $statuses[] = $status;
            }
            $healthTargets[] = ['url' => $target['url'], 'status' => $statuses, 'marker' => $target['marker']];
        }

        return [$request['artifact_url'], $sha256, $healthTargets];
    }

    /** @return array<string,string> */
    private function readEnvironment(string $path): array
    {
        $lines = @file($path, FILE_IGNORE_NEW_LINES);
        if (! is_array($lines)) {
            throw new RuntimeException('invalid_configuration');
        }

        $values = [];
        foreach ($lines as $line) {
            if (! is_string($line) || trim($line) === '' || str_starts_with(ltrim($line), '#')) {
                continue;
            }
            if (preg_match('/\A\s*(?:export\s+)?([A-Z_][A-Z0-9_]*)\s*=\s*(.*)\z/', $line, $matches) !== 1) {
                continue;
            }
            $key = $matches[1];
            if (! in_array($key, ['DEPLOY_SECRET', 'DEPLOY_HEALTH_URLS'], true)) {
                continue;
            }
            if (array_key_exists($key, $values)) {
                throw new RuntimeException('invalid_configuration');
            }

            $value = trim($matches[2]);
            $length = strlen($value);
            if ($length >= 2 && (($value[0] === "'" && $value[$length - 1] === "'") || ($value[0] === '"' && $value[$length - 1] === '"'))) {
                $quote = $value[0];
                $value = substr($value, 1, -1);
                if ($quote === '"') {
                    $value = stripcslashes($value);
                }
            }
            $values[$key] = $value;
        }

        return $values;
    }

    private function download(string $url, string $destination): void
    {
        $temporary = $destination.'.part';
        $this->removeFile($temporary);

        for ($redirects = 0; $redirects <= self::MAX_REDIRECTS; $redirects++) {
            $context = stream_context_create([
                'http' => ['method' => 'GET', 'timeout' => 120, 'ignore_errors' => true, 'follow_location' => 0, 'header' => "User-Agent: LumaDent-Deployer/1\r\n"],
                'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
            ]);
            $source = @fopen($url, 'rb', false, $context);
            $headers = $http_response_header ?? [];
            $status = $this->httpStatus($headers);

            if ($status >= 300 && $status < 400) {
                if (is_resource($source)) {
                    fclose($source);
                }
                $location = $this->header($headers, 'location');
                $parts = is_string($location) ? parse_url($location) : false;
                if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https' || ! isset($parts['host'])) {
                    throw new RuntimeException('artifact_download_failed');
                }
                $url = $location;

                continue;
            }
            if (! is_resource($source) || $status < 200 || $status >= 300) {
                if (is_resource($source)) {
                    fclose($source);
                }
                throw new RuntimeException('artifact_download_failed');
            }

            $target = @fopen($temporary, 'wb');
            if (! is_resource($target)) {
                fclose($source);
                throw new RuntimeException('artifact_download_failed');
            }
            $copied = stream_copy_to_stream($source, $target);
            fclose($source);
            fclose($target);
            if (! is_int($copied) || $copied <= 0 || ! @rename($temporary, $destination)) {
                $this->removeFile($temporary);
                throw new RuntimeException('artifact_download_failed');
            }

            return;
        }

        throw new RuntimeException('artifact_download_failed');
    }

    private function extractInnerRelease(string $artifactPath, string $releasePath): void
    {
        $zip = new ZipArchive;
        if ($zip->open($artifactPath) !== true || $zip->locateName('release.zip', ZipArchive::FL_NOCASE) === false) {
            throw new RuntimeException('artifact_invalid');
        }

        $matches = 0;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            if (strcasecmp((string) $zip->getNameIndex($index), 'release.zip') === 0) {
                $matches++;
            }
        }
        if ($matches !== 1) {
            $zip->close();
            throw new RuntimeException('artifact_invalid');
        }

        $source = $zip->getStream('release.zip');
        $target = @fopen($releasePath.'.part', 'wb');
        if (! is_resource($source) || ! is_resource($target)) {
            is_resource($source) && fclose($source);
            is_resource($target) && fclose($target);
            $zip->close();
            throw new RuntimeException('artifact_invalid');
        }
        $copied = stream_copy_to_stream($source, $target);
        fclose($source);
        fclose($target);
        $zip->close();
        if (! is_int($copied) || $copied <= 0 || ! @rename($releasePath.'.part', $releasePath)) {
            $this->removeFile($releasePath.'.part');
            throw new RuntimeException('artifact_invalid');
        }
    }

    /** @return list<array{index:int,name:string,target:string,directory:bool}> */
    private function inspectRelease(string $releasePath): array
    {
        $zip = new ZipArchive;
        if ($zip->open($releasePath) !== true) {
            throw new RuntimeException('release_invalid');
        }
        $entries = [];
        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = $zip->getNameIndex($index);
                if (! is_string($name) || $this->unsafeArchivePath($name) || $this->isSymbolicLink($zip, $index)) {
                    throw new RuntimeException('release_invalid');
                }
                if ($name === 'release-manifest.json') {
                    continue;
                }
                if ($this->isPersistentUploadPath($name)) {
                    throw new RuntimeException('release_invalid');
                }
                if (strcasecmp(rtrim($name, '/'), 'public_html/deploy.php') === 0) {
                    throw new RuntimeException('release_invalid');
                }

                $directory = str_ends_with($name, '/');
                if (str_starts_with($name, 'lumadent/')) {
                    $relative = substr($name, strlen('lumadent/'));
                    $base = $this->lumadentDirectory;
                } elseif (str_starts_with($name, 'public_html/')) {
                    $relative = substr($name, strlen('public_html/'));
                    $base = $this->publicDirectory;
                } else {
                    throw new RuntimeException('release_invalid');
                }
                $relative = rtrim($relative, '/');
                if ($relative === '') {
                    continue;
                }
                $entries[] = ['index' => $index, 'name' => $name, 'target' => $base.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative), 'directory' => $directory];
            }
        } finally {
            $zip->close();
        }
        if ($entries === []) {
            throw new RuntimeException('release_invalid');
        }

        return $entries;
    }

    private function unsafeArchivePath(string $name): bool
    {
        if ($name === '' || str_contains($name, "\0") || str_contains($name, '\\') || str_starts_with($name, '/') || preg_match('/\A[A-Za-z]:/', $name) === 1) {
            return true;
        }
        foreach (explode('/', rtrim($name, '/')) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return true;
            }
        }

        return false;
    }

    private function isSymbolicLink(ZipArchive $zip, int $index): bool
    {
        $attributes = 0;
        $operationsSystem = 0;
        if (! $zip->getExternalAttributesIndex($index, $operationsSystem, $attributes)) {
            return false;
        }

        return (($attributes >> 16) & 0170000) === 0120000;
    }

    private function createBackup(string $rollbackPath): void
    {
        $zip = new ZipArchive;
        if ($zip->open($rollbackPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('backup_failed');
        }
        try {
            $this->addTreeToZip($zip, $this->lumadentDirectory, 'lumadent');
            $this->addTreeToZip($zip, $this->publicDirectory, 'public_html');
        } catch (Throwable $exception) {
            $zip->close();
            throw $exception;
        }
        if (! $zip->close()) {
            throw new RuntimeException('backup_failed');
        }
    }

    private function addTreeToZip(ZipArchive $zip, string $root, string $prefix): void
    {
        $directory = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);
        $filter = new RecursiveCallbackFilterIterator($directory, function ($item) use ($prefix, $root): bool {
            $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));

            return ! ($prefix === 'public_html' && $this->isPersistentUploadPath($prefix.'/'.$relative));
        });
        $iterator = new RecursiveIteratorIterator($filter, RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $item) {
            $path = $item->getPathname();
            $relative = str_replace('\\', '/', substr($path, strlen($root) + 1));
            $archiveName = $prefix.'/'.$relative;
            if ($item->isLink()) {
                throw new RuntimeException('backup_failed');
            }
            if ($item->isDir()) {
                if (! $zip->addEmptyDir($archiveName)) {
                    throw new RuntimeException('backup_failed');
                }
            } elseif ($item->isFile() && ! $zip->addFile($path, $archiveName)) {
                throw new RuntimeException('backup_failed');
            }
        }
    }

    /** @param list<array{index:int,name:string,target:string,directory:bool}> $entries */
    private function extractRelease(string $releasePath, array $entries): void
    {
        $zip = new ZipArchive;
        if ($zip->open($releasePath) !== true) {
            throw new RuntimeException('release_extract_failed');
        }
        try {
            foreach ($entries as $entry) {
                if ($entry['directory']) {
                    $this->createDirectory($entry['target']);

                    continue;
                }
                $this->createDirectory(dirname($entry['target']));
                $source = $zip->getStream($entry['name']);
                $target = @fopen($entry['target'], 'wb');
                if (! is_resource($source) || ! is_resource($target)) {
                    is_resource($source) && fclose($source);
                    is_resource($target) && fclose($target);
                    throw new RuntimeException('release_extract_failed');
                }
                $result = stream_copy_to_stream($source, $target);
                fclose($source);
                fclose($target);
                if (! is_int($result)) {
                    throw new RuntimeException('release_extract_failed');
                }
            }
        } finally {
            $zip->close();
        }
    }

    /** @param list<array{url:string,status:list<int>,marker:string}> $targets */
    private function runHealthChecks(array $targets): void
    {
        foreach ($targets as $target) {
            $response = ($this->healthTransport)($target['url']);
            if (! is_array($response) || ! is_int($response['status'] ?? null) || ! is_string($response['body'] ?? null)
                || ! in_array($response['status'], $target['status'], true)
                || ($target['marker'] !== '' && ! str_contains($response['body'], $target['marker']))) {
                throw new RuntimeException('health_check_failed');
            }
        }
    }

    /** @return array{status:int,body:string} */
    private function healthGet(string $url): array
    {
        for ($redirects = 0; $redirects <= self::MAX_REDIRECTS; $redirects++) {
            $context = stream_context_create([
                'http' => ['method' => 'GET', 'timeout' => 20, 'ignore_errors' => true, 'follow_location' => 0, 'header' => "User-Agent: LumaDent-Health-Check/1\r\n"],
                'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
            ]);
            $body = @file_get_contents($url, false, $context, 0, self::MAX_HEALTH_BODY + 1);
            $headers = $http_response_header ?? [];
            $status = $this->httpStatus($headers);
            if ($status >= 300 && $status < 400) {
                $location = $this->header($headers, 'location');
                $parts = is_string($location) ? parse_url($location) : false;
                if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https' || ! isset($parts['host'])) {
                    throw new RuntimeException('health_check_failed');
                }
                $url = $location;

                continue;
            }
            if (! is_string($body) || strlen($body) > self::MAX_HEALTH_BODY) {
                throw new RuntimeException('health_check_failed');
            }

            return ['status' => $status, 'body' => $body];
        }
        throw new RuntimeException('health_check_failed');
    }

    /** @param list<array{index:int,name:string,target:string,directory:bool}> $entries */
    private function removeReleaseTargets(array $entries): void
    {
        usort($entries, static fn (array $left, array $right): int => strlen($right['target']) <=> strlen($left['target']));
        foreach ($entries as $entry) {
            if (! $entry['directory'] && (is_file($entry['target']) || is_link($entry['target'])) && ! @unlink($entry['target'])) {
                throw new RuntimeException('rollback_failed');
            }
        }
        foreach ($entries as $entry) {
            $directory = $entry['directory'] ? $entry['target'] : dirname($entry['target']);
            if (is_dir($directory) && array_diff(scandir($directory) ?: [], ['.', '..']) === []) {
                rmdir($directory);
            }
        }
    }

    private function restoreBackup(string $rollbackPath): void
    {
        $zip = new ZipArchive;
        if ($zip->open($rollbackPath) !== true) {
            throw new RuntimeException('rollback_failed');
        }
        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = $zip->getNameIndex($index);
                if (! is_string($name) || $this->unsafeArchivePath($name) || $this->isSymbolicLink($zip, $index)) {
                    throw new RuntimeException('rollback_failed');
                }
                if ($this->isPersistentUploadPath($name)) {
                    continue;
                }
                if (strcasecmp(rtrim($name, '/'), 'public_html/deploy.php') === 0) {
                    continue;
                }
                if (str_starts_with($name, 'lumadent/')) {
                    $relative = substr($name, strlen('lumadent/'));
                    $base = $this->lumadentDirectory;
                } elseif (str_starts_with($name, 'public_html/')) {
                    $relative = substr($name, strlen('public_html/'));
                    $base = $this->publicDirectory;
                } else {
                    throw new RuntimeException('rollback_failed');
                }
                $relative = rtrim($relative, '/');
                if ($relative === '') {
                    continue;
                }
                $targetPath = $base.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
                if (str_ends_with($name, '/')) {
                    $this->createDirectory($targetPath);

                    continue;
                }
                $this->createDirectory(dirname($targetPath));
                $source = $zip->getStream($name);
                $target = @fopen($targetPath, 'wb');
                if (! is_resource($source) || ! is_resource($target)) {
                    is_resource($source) && fclose($source);
                    is_resource($target) && fclose($target);
                    throw new RuntimeException('rollback_failed');
                }
                $result = stream_copy_to_stream($source, $target);
                fclose($source);
                fclose($target);
                if (! is_int($result)) {
                    throw new RuntimeException('rollback_failed');
                }
            }
        } finally {
            $zip->close();
        }
    }

    private function createDirectory(string $path): void
    {
        if (file_exists($path) && ! is_dir($path)) {
            throw new RuntimeException('filesystem_write_failed');
        }
        if (! is_dir($path) && ! mkdir($path, 0775, true) && ! is_dir($path)) {
            throw new RuntimeException('filesystem_write_failed');
        }
    }

    private function isPersistentUploadPath(string $path): bool
    {
        $normalized = strtolower(rtrim($path, '/'));

        return $normalized === 'public_html/uploads' || str_starts_with($normalized, 'public_html/uploads/');
    }

    private function removeFile(string $path): void
    {
        if (is_file($path) && ! @unlink($path)) {
            throw new RuntimeException('filesystem_write_failed');
        }
    }

    private function httpStatus(array $headers): int
    {
        foreach ($headers as $header) {
            if (is_string($header) && preg_match('/\AHTTP\/\S+\s+([0-9]{3})\b/i', $header, $matches) === 1) {
                return (int) $matches[1];
            }
        }
        throw new RuntimeException('http_request_failed');
    }

    private function header(array $headers, string $wanted): ?string
    {
        foreach ($headers as $header) {
            if (is_string($header) && str_contains($header, ':')) {
                [$name, $value] = explode(':', $header, 2);
                if (strcasecmp(trim($name), $wanted) === 0) {
                    return trim($value);
                }
            }
        }

        return null;
    }

    private function log(Throwable $deploymentFailure, ?Throwable $rollbackFailure = null): void
    {
        $messages = ['deployment='.$this->sanitize($deploymentFailure->getMessage())];
        if ($rollbackFailure !== null) {
            $messages[] = 'rollback='.$this->sanitize($rollbackFailure->getMessage());
        }
        @file_put_contents($this->deployerDirectory.DIRECTORY_SEPARATOR.'deploy.log', gmdate('c').' '.implode(' ', $messages).PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private function sanitize(string $message): string
    {
        $message = preg_replace('/https:\/\/\S+/i', '[redacted-url]', $message) ?? 'error';

        return preg_replace('/[\r\n]+/', ' ', $message) ?? 'error';
    }

    /** @return array{status:int,body:array{deployment:array{status:string}}} */
    private function response(int $status, string $deploymentStatus): array
    {
        return ['status' => $status, 'body' => ['deployment' => ['status' => $deploymentStatus]]];
    }
}

final class UnauthorizedDeployment extends RuntimeException {}

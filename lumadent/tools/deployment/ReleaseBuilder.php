<?php

namespace Lumadent\Deployment;

use FilesystemIterator;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

final class ReleaseBuilder
{
    private const REQUIRED_FILES = [
        'lumadent/artisan',
        'lumadent/bootstrap/app.php',
        'lumadent/vendor/autoload.php',
        'public_html/.htaccess',
        'public_html/index.php',
        'public_html/build/manifest.json',
    ];

    private const EXCLUDED_PATHS = [
        '.git',
        '.github',
        '.editorconfig',
        '.gitattributes',
        '.gitignore',
        '.fleet',
        '.idea',
        '.nova',
        '.phpunit.cache',
        '.phpunit.result.cache',
        '.vscode',
        '.zed',
        'README.md',
        'deployer',
        'lumadent/.env',
        'lumadent/.env.backup',
        'lumadent/.env.production',
        'lumadent/.phpunit.cache',
        'lumadent/.phpunit.result.cache',
        'lumadent/build',
        'lumadent/node_modules',
        'lumadent/tests',
        'lumadent/tools',
        'lumadent/vendor/bin',
        'public_html/.env',
        'public_html/hot',
        'public_html/deploy.php',
        'public_html/storage',
        'public_html/uploads',
    ];

    /**
     * @param  array{release_id:string,run_id:string|int,built_at:string}  $metadata
     * @return array{schema:int,release_id:string,run_id:string,built_at:string,php:string,files:list<array{path:string,sha256:string,size:int}>}
     */
    public function build(string $source, string $destination, array $metadata): array
    {
        $source = $this->existingDirectory($source);
        $this->validateMetadata($metadata);
        $this->prepareEmptyDirectory($destination);

        $releaseDirectory = $destination.DIRECTORY_SEPARATOR.'release';
        $this->createDirectory($releaseDirectory);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($iterator as $file) {
            $relative = $this->relativePath($source, $file->getPathname());

            if ($this->isExcluded($relative)) {
                continue;
            }

            if ($file->isLink()) {
                throw new RuntimeException("Release source contains a symbolic link: {$relative}");
            }

            if (! $file->isFile()) {
                continue;
            }

            $target = $releaseDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $this->createDirectory(dirname($target));

            if (! copy($file->getPathname(), $target)) {
                throw new RuntimeException("Unable to copy release file: {$relative}");
            }
        }

        $this->createDirectory($releaseDirectory.DIRECTORY_SEPARATOR.'lumadent'.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache');

        foreach (self::REQUIRED_FILES as $required) {
            if (! is_file($releaseDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $required))) {
                throw new RuntimeException("Required release file is missing: {$required}");
            }
        }

        $files = $this->fileManifest($releaseDirectory);
        $manifest = [
            'schema' => 1,
            'release_id' => $metadata['release_id'],
            'run_id' => (string) $metadata['run_id'],
            'built_at' => $metadata['built_at'],
            'php' => '8.3.0',
            'files' => $files,
        ];

        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
        if (file_put_contents($releaseDirectory.DIRECTORY_SEPARATOR.'release-manifest.json', $json, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write the release manifest.');
        }

        return $manifest;
    }

    public function createArchive(string $destination): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP Zip extension is required.');
        }

        $releaseDirectory = $this->existingDirectory($destination.DIRECTORY_SEPARATOR.'release');
        $archivePath = $destination.DIRECTORY_SEPARATOR.'release.zip';
        $zip = new ZipArchive;

        if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create the release archive.');
        }

        $paths = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($releaseDirectory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $paths[$this->relativePath($releaseDirectory, $file->getPathname())] = $file->getPathname();
            }
        }
        ksort($paths, SORT_STRING);

        foreach ($paths as $relative => $absolute) {
            if (! $zip->addFile($absolute, $relative)) {
                $zip->close();
                throw new RuntimeException("Unable to add archive file: {$relative}");
            }
        }

        if (! $zip->close()) {
            throw new RuntimeException('Unable to finalize the release archive.');
        }

        $checksum = hash_file('sha256', $archivePath);
        if (! is_string($checksum) || file_put_contents($destination.DIRECTORY_SEPARATOR.'release.sha256', $checksum.PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write the release checksum.');
        }

        return $archivePath;
    }

    private function existingDirectory(string $path): string
    {
        $real = realpath($path);
        if ($real === false || ! is_dir($real)) {
            throw new InvalidArgumentException("Directory does not exist: {$path}");
        }

        return rtrim($real, DIRECTORY_SEPARATOR);
    }

    /** @param array{release_id?:mixed,run_id?:mixed,built_at?:mixed} $metadata */
    private function validateMetadata(array $metadata): void
    {
        if (! is_string($metadata['release_id'] ?? null) || preg_match('/\A[a-f0-9]{40}\z/', $metadata['release_id']) !== 1) {
            throw new InvalidArgumentException('Release ID must be a lowercase 40-character hexadecimal value.');
        }
        if (! is_scalar($metadata['run_id'] ?? null) || preg_match('/\A[0-9]+\z/', (string) $metadata['run_id']) !== 1) {
            throw new InvalidArgumentException('Run ID must be numeric.');
        }
        if (! is_string($metadata['built_at'] ?? null) || strtotime($metadata['built_at']) === false) {
            throw new InvalidArgumentException('Build time must be a valid date.');
        }
    }

    private function prepareEmptyDirectory(string $path): void
    {
        if (file_exists($path)) {
            $entries = array_diff(scandir($path) ?: [], ['.', '..']);
            if ($entries !== []) {
                throw new RuntimeException('Release output directory must be empty.');
            }
        }
        $this->createDirectory($path);
    }

    private function createDirectory(string $path): void
    {
        if (! is_dir($path) && ! mkdir($path, 0775, true) && ! is_dir($path)) {
            throw new RuntimeException("Unable to create directory: {$path}");
        }
    }

    private function relativePath(string $root, string $path): string
    {
        return str_replace('\\', '/', substr($path, strlen($root) + 1));
    }

    private function isExcluded(string $path): bool
    {
        foreach (self::EXCLUDED_PATHS as $excluded) {
            if ($path === $excluded || str_starts_with($path, $excluded.'/')) {
                return true;
            }
        }

        return false;
    }

    /** @return list<array{path:string,sha256:string,size:int}> */
    private function fileManifest(string $releaseDirectory): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($releaseDirectory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }
            $relative = $this->relativePath($releaseDirectory, $file->getPathname());
            $hash = hash_file('sha256', $file->getPathname());
            if (! is_string($hash)) {
                throw new RuntimeException("Unable to hash release file: {$relative}");
            }
            $files[] = ['path' => $relative, 'sha256' => $hash, 'size' => $file->getSize()];
        }
        usort($files, static fn (array $left, array $right): int => strcmp($left['path'], $right['path']));

        return $files;
    }
}

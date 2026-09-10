<?php

namespace Tests\Unit\Deployment;

require_once __DIR__.'/../../../../deployer/SharedHostDeployer.php';

use Lumadent\Deployer\SharedHostDeployer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class SharedHostDeployerTest extends TestCase
{
    private string $root;

    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'lumadent-deployer-'.bin2hex(random_bytes(6));
        $this->secret = str_repeat('s', 32);
        foreach (['deployer', 'lumadent', 'public_html', 'public_html/uploads'] as $directory) {
            mkdir($this->root.DIRECTORY_SEPARATOR.$directory, 0775, true);
        }
        file_put_contents($this->root.'/lumadent/.env', "DEPLOY_SECRET={$this->secret}\nDEPLOY_HEALTH_URLS='[{\"url\":\"https://clinic.example/up\",\"status\":[200],\"marker\":\"Healthy\"}]'\n");
        file_put_contents($this->root.'/lumadent/current.php', 'old-private');
        file_put_contents($this->root.'/public_html/index.php', 'old-public');
        file_put_contents($this->root.'/public_html/deploy.php', 'protected-endpoint');
        file_put_contents($this->root.'/public_html/uploads/existing.jpg', 'dynamic-upload');
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
        parent::tearDown();
    }

    public function test_it_authenticates_downloads_deploys_and_checks_health(): void
    {
        [$artifact, $sha256] = $this->artifact([
            'release-manifest.json' => '{}',
            'lumadent/current.php' => 'new-private',
            'lumadent/new.php' => 'new-file',
            'public_html/index.php' => 'new-public',
        ]);
        $healthCalls = [];
        $deployer = $this->deployer($artifact, static function (string $url) use (&$healthCalls): array {
            $healthCalls[] = $url;

            return ['status' => 200, 'body' => 'Healthy'];
        });

        $result = $deployer->handle($this->request($artifact, $sha256));

        self::assertSame(200, $result['status']);
        self::assertSame('succeeded', $result['body']['deployment']['status']);
        self::assertSame('new-private', file_get_contents($this->root.'/lumadent/current.php'));
        self::assertSame('new-public', file_get_contents($this->root.'/public_html/index.php'));
        self::assertSame('new-file', file_get_contents($this->root.'/lumadent/new.php'));
        self::assertStringContainsString('DEPLOY_SECRET=', (string) file_get_contents($this->root.'/lumadent/.env'));
        self::assertSame('protected-endpoint', file_get_contents($this->root.'/public_html/deploy.php'));
        self::assertSame('dynamic-upload', file_get_contents($this->root.'/public_html/uploads/existing.jpg'));
        self::assertFileExists($this->root.'/deployer/rollback.zip');
        self::assertBackupDoesNotContainUploads();
        self::assertSame(['https://clinic.example/up'], $healthCalls);
    }

    public function test_it_rejects_an_invalid_signature_before_downloading(): void
    {
        [$artifact, $sha256] = $this->artifact(['lumadent/current.php' => 'new-private']);
        $downloaded = false;
        $deployer = new SharedHostDeployer($this->root, static function () use (&$downloaded): void {
            $downloaded = true;
        }, static fn (): array => ['status' => 200, 'body' => 'Healthy']);
        $request = $this->request($artifact, $sha256);
        $request['signature'] = str_repeat('0', 64);

        $result = $deployer->handle($request);

        self::assertSame(401, $result['status']);
        self::assertSame('unauthorized', $result['body']['deployment']['status']);
        self::assertFalse($downloaded);
    }

    public function test_it_rejects_a_checksum_mismatch_before_creating_a_backup(): void
    {
        [$artifact] = $this->artifact(['lumadent/current.php' => 'new-private']);
        $deployer = $this->deployer($artifact);

        $result = $deployer->handle($this->request($artifact, str_repeat('a', 64)));

        self::assertSame(500, $result['status']);
        self::assertSame('failed', $result['body']['deployment']['status']);
        self::assertSame('old-private', file_get_contents($this->root.'/lumadent/current.php'));
        self::assertFileDoesNotExist($this->root.'/deployer/rollback.zip');
    }

    #[DataProvider('unsafeArchiveEntries')]
    public function test_it_rejects_unsafe_release_entries(string $name): void
    {
        [$artifact, $sha256] = $this->artifact([$name => 'unsafe']);
        $result = $this->deployer($artifact)->handle($this->request($artifact, $sha256));

        self::assertSame(500, $result['status']);
        self::assertSame('failed', $result['body']['deployment']['status']);
        self::assertSame('protected-endpoint', file_get_contents($this->root.'/public_html/deploy.php'));
    }

    /** @return array<string,array{string}> */
    public static function unsafeArchiveEntries(): array
    {
        return [
            'parent traversal' => ['../escape.php'],
            'absolute path' => ['/absolute.php'],
            'windows path' => ['C:/absolute.php'],
            'unexpected root' => ['other/file.php'],
            'protected endpoint' => ['public_html/deploy.php'],
            'persistent uploads' => ['public_html/uploads/replaced.jpg'],
        ];
    }

    public function test_it_rejects_a_symbolic_link_entry(): void
    {
        [$artifact, $sha256] = $this->artifact(['lumadent/link' => 'target'], 'lumadent/link');
        $result = $this->deployer($artifact)->handle($this->request($artifact, $sha256));

        self::assertSame(500, $result['status']);
        self::assertSame('failed', $result['body']['deployment']['status']);
    }

    public function test_it_rolls_back_new_and_overwritten_files_after_a_health_failure(): void
    {
        [$artifact, $sha256] = $this->artifact([
            'lumadent/current.php' => 'new-private',
            'lumadent/new.php' => 'new-file',
            'public_html/index.php' => 'new-public',
        ]);
        $deployer = $this->deployer($artifact, static fn (): array => ['status' => 500, 'body' => 'Broken']);

        $result = $deployer->handle($this->request($artifact, $sha256));

        self::assertSame(422, $result['status']);
        self::assertSame('rolled_back', $result['body']['deployment']['status']);
        self::assertSame('old-private', file_get_contents($this->root.'/lumadent/current.php'));
        self::assertSame('old-public', file_get_contents($this->root.'/public_html/index.php'));
        self::assertFileDoesNotExist($this->root.'/lumadent/new.php');
        self::assertSame('protected-endpoint', file_get_contents($this->root.'/public_html/deploy.php'));
        self::assertSame('dynamic-upload', file_get_contents($this->root.'/public_html/uploads/existing.jpg'));
        self::assertBackupDoesNotContainUploads();
    }

    public function test_it_rolls_back_after_an_extraction_failure(): void
    {
        file_put_contents($this->root.'/lumadent/conflict', 'existing-file');
        [$artifact, $sha256] = $this->artifact([
            'lumadent/current.php' => 'new-private',
            'lumadent/conflict/new.php' => 'cannot-write',
        ]);

        $result = $this->deployer($artifact)->handle($this->request($artifact, $sha256));

        self::assertSame(422, $result['status']);
        self::assertSame('old-private', file_get_contents($this->root.'/lumadent/current.php'));
        self::assertSame('existing-file', file_get_contents($this->root.'/lumadent/conflict'));
    }

    public function test_it_logs_and_stops_when_rollback_fails(): void
    {
        [$artifact, $sha256] = $this->artifact(['lumadent/current.php' => 'new-private']);
        $root = $this->root;
        $deployer = $this->deployer($artifact, static function () use ($root): array {
            unlink($root.'/deployer/rollback.zip');

            return ['status' => 500, 'body' => 'Broken'];
        });

        $result = $deployer->handle($this->request($artifact, $sha256));

        self::assertSame(500, $result['status']);
        self::assertSame('rollback_failed', $result['body']['deployment']['status']);
        self::assertFileExists($this->root.'/deployer/deploy.log');
        $log = (string) file_get_contents($this->root.'/deployer/deploy.log');
        self::assertStringContainsString('rollback=rollback_failed', $log);
        self::assertStringNotContainsString($this->secret, $log);
        self::assertStringNotContainsString('https://clinic.example', $log);
    }

    public function test_it_rejects_a_concurrent_deployment(): void
    {
        [$artifact, $sha256] = $this->artifact(['lumadent/current.php' => 'new-private']);
        $lock = fopen($this->root.'/deployer/deploy.lock', 'c+');
        self::assertIsResource($lock);
        flock($lock, LOCK_EX);

        try {
            $result = $this->deployer($artifact)->handle($this->request($artifact, $sha256));
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        self::assertSame(409, $result['status']);
        self::assertSame('busy', $result['body']['deployment']['status']);
        self::assertSame('old-private', file_get_contents($this->root.'/lumadent/current.php'));
    }

    public function test_it_rejects_invalid_private_configuration(): void
    {
        file_put_contents($this->root.'/lumadent/.env', "DEPLOY_SECRET=short\nDEPLOY_HEALTH_URLS=[]\n");
        [$artifact, $sha256] = $this->artifact(['lumadent/current.php' => 'new-private']);
        $result = $this->deployer($artifact)->handle($this->request($artifact, $sha256));

        self::assertSame(400, $result['status']);
        self::assertSame('invalid_request', $result['body']['deployment']['status']);
    }

    public function test_public_endpoint_rejects_get_requests(): void
    {
        $result = $this->runEndpoint('GET', '');

        self::assertSame(0, $result['exit']);
        self::assertSame('{"deployment":{"status":"method_not_allowed"}}', $result['output']);
    }

    public function test_public_endpoint_rejects_malformed_json(): void
    {
        $result = $this->runEndpoint('POST', 'not-json');

        self::assertSame(0, $result['exit']);
        self::assertSame('{"deployment":{"status":"invalid_request"}}', $result['output']);
    }

    private function deployer(string $artifact, ?callable $health = null): SharedHostDeployer
    {
        return new SharedHostDeployer(
            $this->root,
            static function (string $url, string $destination) use ($artifact): void {
                if (! copy($artifact, $destination)) {
                    throw new \RuntimeException('fixture_copy_failed');
                }
            },
            $health ?? static fn (): array => ['status' => 200, 'body' => 'Healthy'],
        );
    }

    /** @return array{string,string} */
    private function artifact(array $files, ?string $symbolicLink = null): array
    {
        $id = bin2hex(random_bytes(4));
        $release = $this->root."/fixture-release-{$id}.zip";
        $artifact = $this->root."/fixture-artifact-{$id}.zip";
        $releaseZip = new ZipArchive;
        self::assertTrue($releaseZip->open($release, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
        foreach ($files as $name => $contents) {
            self::assertTrue($releaseZip->addFromString($name, $contents));
        }
        if ($symbolicLink !== null) {
            self::assertTrue($releaseZip->setExternalAttributesName($symbolicLink, ZipArchive::OPSYS_UNIX, 0120777 << 16));
        }
        self::assertTrue($releaseZip->close());

        $sha256 = hash_file('sha256', $release);
        self::assertIsString($sha256);
        $artifactZip = new ZipArchive;
        self::assertTrue($artifactZip->open($artifact, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
        self::assertTrue($artifactZip->addFile($release, 'release.zip'));
        self::assertTrue($artifactZip->addFromString('release.sha256', $sha256."\n"));
        self::assertTrue($artifactZip->close());

        return [$artifact, $sha256];
    }

    private function request(string $artifact, string $sha256): array
    {
        $url = 'https://artifact.example.test/'.basename($artifact);
        $salt = str_repeat('ab', 16);

        return [
            'artifact_url' => $url,
            'sha256' => $sha256,
            'salt' => $salt,
            'signature' => hash_hmac('sha256', $url."\n".$sha256."\n".$salt, $this->secret),
        ];
    }

    /** @return array{exit:int,output:string} */
    private function runEndpoint(string $method, string $input): array
    {
        $command = [PHP_BINARY, dirname(__DIR__, 4).'/public_html/deploy.php'];
        $environment = array_merge($_ENV, ['REQUEST_METHOD' => $method]);
        $process = proc_open($command, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, null, $environment);
        self::assertIsResource($process);
        fwrite($pipes[0], $input);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        self::assertSame('', $errors);
        self::assertIsString($output);

        return ['exit' => $exit, 'output' => $output];
    }

    private function assertBackupDoesNotContainUploads(): void
    {
        $zip = new ZipArchive;
        self::assertTrue($zip->open($this->root.'/deployer/rollback.zip') === true);
        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = (string) $zip->getNameIndex($index);
                self::assertFalse($name === 'public_html/uploads' || str_starts_with($name, 'public_html/uploads/'));
            }
        } finally {
            $zip->close();
        }
    }

    private function remove(string $path): void
    {
        if (! file_exists($path) && ! is_link($path)) {
            return;
        }
        if (is_dir($path) && ! is_link($path)) {
            foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
                $this->remove($path.DIRECTORY_SEPARATOR.$entry);
            }
            rmdir($path);

            return;
        }
        unlink($path);
    }
}

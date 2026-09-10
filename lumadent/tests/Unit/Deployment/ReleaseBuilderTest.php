<?php

namespace Tests\Unit\Deployment;

require_once __DIR__.'/../../../tools/deployment/ReleaseBuilder.php';

use Lumadent\Deployment\ReleaseBuilder;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ReleaseBuilderTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->temporaryDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'lumadent-release-'.bin2hex(random_bytes(6));
        mkdir($this->temporaryDirectory, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->remove($this->temporaryDirectory);
        parent::tearDown();
    }

    public function test_it_builds_a_clean_deterministic_release_tree(): void
    {
        $source = $this->temporaryDirectory.DIRECTORY_SEPARATOR.'source';
        $output = $this->temporaryDirectory.DIRECTORY_SEPARATOR.'output';
        $this->createFixture($source);

        $manifest = (new ReleaseBuilder)->build($source, $output, [
            'release_id' => str_repeat('a', 40),
            'run_id' => '412',
            'built_at' => '2026-09-10T10:00:00Z',
        ]);

        self::assertFileExists($output.'/release/lumadent/artisan');
        self::assertFileExists($output.'/release/lumadent/vendor/autoload.php');
        self::assertFileExists($output.'/release/public_html/build/manifest.json');
        self::assertFileDoesNotExist($output.'/release/public_html/deploy.php');
        self::assertFileDoesNotExist($output.'/release/public_html/uploads/example.jpg');
        self::assertFileDoesNotExist($output.'/release/deployer/deploy.php');
        self::assertFileDoesNotExist($output.'/release/lumadent/.env');
        self::assertFileDoesNotExist($output.'/release/lumadent/tests/Test.php');
        self::assertFileDoesNotExist($output.'/release/lumadent/node_modules/module.js');
        self::assertSame(str_repeat('a', 40), $manifest['release_id']);
        self::assertSame(['lumadent/artisan', 'lumadent/bootstrap/app.php', 'lumadent/vendor/autoload.php', 'public_html/.htaccess', 'public_html/build/manifest.json', 'public_html/index.php'], array_column($manifest['files'], 'path'));
    }

    public function test_it_creates_a_zip_and_checksum(): void
    {
        if (! class_exists(\ZipArchive::class)) {
            self::markTestSkipped('The PHP Zip extension is unavailable.');
        }

        $source = $this->temporaryDirectory.'/source';
        $output = $this->temporaryDirectory.'/output';
        $this->createFixture($source);
        $builder = new ReleaseBuilder;
        $builder->build($source, $output, [
            'release_id' => str_repeat('b', 40),
            'run_id' => '413',
            'built_at' => '2026-09-10T10:00:00Z',
        ]);

        $archive = $builder->createArchive($output);

        self::assertFileExists($archive);
        self::assertSame(hash_file('sha256', $archive), trim((string) file_get_contents($output.'/release.sha256')));
    }

    public function test_it_rejects_a_missing_required_file(): void
    {
        $source = $this->temporaryDirectory.'/source';
        $output = $this->temporaryDirectory.'/output';
        $this->createFixture($source);
        unlink($source.'/lumadent/vendor/autoload.php');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('lumadent/vendor/autoload.php');

        (new ReleaseBuilder)->build($source, $output, [
            'release_id' => str_repeat('c', 40),
            'run_id' => '414',
            'built_at' => '2026-09-10T10:00:00Z',
        ]);
    }

    private function createFixture(string $root): void
    {
        foreach (['deployer', 'lumadent/bootstrap', 'lumadent/vendor', 'lumadent/tests', 'lumadent/node_modules', 'public_html/build', 'public_html/uploads'] as $directory) {
            mkdir($root.'/'.$directory, 0775, true);
        }
        foreach ([
            'lumadent/artisan' => 'artisan',
            'lumadent/bootstrap/app.php' => '<?php return true;',
            'lumadent/vendor/autoload.php' => '<?php',
            'lumadent/.env' => 'APP_KEY=secret',
            'lumadent/tests/Test.php' => '<?php',
            'lumadent/node_modules/module.js' => 'module',
            'public_html/index.php' => '<?php echo "ok";',
            'public_html/.htaccess' => 'RewriteEngine On',
            'public_html/build/manifest.json' => '{}',
            'public_html/deploy.php' => '<?php echo "protected";',
            'public_html/uploads/example.jpg' => 'dynamic-upload',
            'deployer/deploy.php' => '<?php echo "private";',
        ] as $path => $content) {
            file_put_contents($root.'/'.$path, $content);
        }
    }

    private function remove(string $path): void
    {
        if (! file_exists($path)) {
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

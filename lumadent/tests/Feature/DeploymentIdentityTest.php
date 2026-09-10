<?php

namespace Tests\Feature;

use Tests\TestCase;

final class DeploymentIdentityTest extends TestCase
{
    public function test_responses_include_the_configured_release_identifier(): void
    {
        config(['app.release_id' => '0123456789abcdef0123456789abcdef01234567']);

        $this->get('/')->assertOk()
            ->assertHeader('X-LumaDent-Release', '0123456789abcdef0123456789abcdef01234567');
    }

    public function test_release_header_is_omitted_when_no_identifier_is_configured(): void
    {
        config(['app.release_id' => null]);

        $this->get('/')->assertOk()->assertHeaderMissing('X-LumaDent-Release');
    }

    public function test_laravel_honours_an_absolute_shared_storage_path(): void
    {
        $shared = sys_get_temp_dir().DIRECTORY_SEPARATOR.'lumadent-shared-storage';
        $_ENV['LARAVEL_STORAGE_PATH'] = $shared;
        $_SERVER['LARAVEL_STORAGE_PATH'] = $shared;

        try {
            $this->assertSame($shared, app()->storagePath());
        } finally {
            unset($_ENV['LARAVEL_STORAGE_PATH'], $_SERVER['LARAVEL_STORAGE_PATH']);
        }
    }
}

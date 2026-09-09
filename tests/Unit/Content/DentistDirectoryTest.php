<?php

namespace Tests\Unit\Content;

use App\Content\DentistDirectory;
use Tests\TestCase;

final class DentistDirectoryTest extends TestCase
{
    public function test_every_dentist_is_an_explicit_sample_profile(): void
    {
        $dentists = app(DentistDirectory::class)->all();

        $this->assertNotEmpty($dentists);

        foreach ($dentists as $dentist) {
            $this->assertTrue($dentist['is_sample']);
            $this->assertArrayHasKey('name', $dentist);
            $this->assertArrayHasKey('role', $dentist);
            $this->assertArrayHasKey('bio', $dentist);
            $this->assertArrayHasKey('services', $dentist);
        }
    }
}

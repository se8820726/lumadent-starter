<?php

namespace Tests\Unit\Content;

use App\Content\ServiceCatalog;
use Tests\TestCase;

final class ServiceCatalogTest extends TestCase
{
    public function test_every_service_has_a_unique_slug_and_required_public_fields(): void
    {
        $services = app(ServiceCatalog::class)->all();
        $slugs = array_column($services, 'slug');

        $this->assertNotEmpty($services);
        $this->assertSameSize(array_unique($slugs), $slugs);

        foreach ($services as $service) {
            $this->assertArrayHasKey('slug', $service);
            $this->assertArrayHasKey('name', $service);
            $this->assertArrayHasKey('summary', $service);
            $this->assertArrayHasKey('description', $service);
            $this->assertArrayHasKey('featured', $service);
        }
    }
}

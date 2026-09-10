<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class PublicPagesTest extends TestCase
{
    public static function publicRoutes(): array
    {
        return [
            ['home'],
            ['services.index'],
            ['dentists.index'],
            ['about'],
            ['contact'],
            ['privacy'],
            ['booking.demo'],
        ];
    }

    #[DataProvider('publicRoutes')]
    public function test_it_serves_every_public_page(string $route): void
    {
        $this->get(route($route))->assertOk();
    }
}

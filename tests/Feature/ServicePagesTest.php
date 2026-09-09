<?php

namespace Tests\Feature;

use Tests\TestCase;

final class ServicePagesTest extends TestCase
{
    public function test_it_serves_a_known_service(): void
    {
        $this->get(route('services.show', 'routine-examinations'))
            ->assertOk()
            ->assertSee('Routine examinations');
    }

    public function test_it_returns_not_found_for_an_unknown_service(): void
    {
        $this->get('/services/not-a-service')->assertNotFound();
    }
}

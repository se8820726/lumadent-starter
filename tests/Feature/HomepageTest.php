<?php

namespace Tests\Feature;

use Tests\TestCase;

final class HomepageTest extends TestCase
{
    public function test_english_homepage_contains_the_complete_marketing_journey(): void
    {
        $response = $this->get('/')->assertOk();

        $response
            ->assertSee('A fictional London clinic, designed to make every step feel clearer.')
            ->assertSee('People behind the care')
            ->assertSee('Everything you need, before you arrive')
            ->assertSee('A simpler way to begin')
            ->assertSee('No personal information is requested, sent or stored.')
            ->assertSee('Sample profile')
            ->assertSee('Central London')
            ->assertSee('Monday to Friday')
            ->assertSee('href="'.url('/dentists').'"', false)
            ->assertSee('href="'.url('/contact').'"', false)
            ->assertSee('href="'.url('/book').'"', false);

        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_arabic_homepage_contains_the_translated_marketing_journey(): void
    {
        $this->get('/ar/')->assertOk()
            ->assertSee('عيادة خيالية في لندن صُمّمت لتجعل كل خطوة أكثر وضوحاً.')
            ->assertSee('الأشخاص وراء الرعاية')
            ->assertSee('كل ما تحتاجه قبل وصولك')
            ->assertSee('بداية أكثر بساطة')
            ->assertSee('لا نطلب أي معلومات شخصية أو نرسلها أو نخزّنها.')
            ->assertSee('ملف تعريفي نموذجي')
            ->assertSee('وسط لندن')
            ->assertSee('href="'.url('/ar/dentists').'"', false)
            ->assertSee('href="'.url('/ar/contact').'"', false)
            ->assertSee('href="'.url('/ar/book').'"', false);
    }

    public function test_homepage_hero_image_is_prioritised_without_navigation_interception(): void
    {
        foreach (['/', '/ar/'] as $path) {
            $response = $this->get($path)->assertOk()
                ->assertSee('src="'.asset('images/clinic/lumadent-reception.webp').'"', false)
                ->assertSee('width="1600"', false)
                ->assertSee('height="900"', false)
                ->assertSee('loading="eager"', false)
                ->assertSee('fetchpriority="high"', false)
                ->assertDontSee('wire:navigate')
                ->assertDontSee('x-on:click');

            $this->assertStringNotContainsString('loading="lazy"', $response->getContent());
        }
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class LocalizationTest extends TestCase
{
    public static function pages(): array
    {
        $pages = ['', 'services', 'dentists', 'about', 'contact', 'privacy', 'book'];
        foreach (array_keys(require __DIR__.'/../../config/treatments.php') as $slug) {
            $pages[] = 'services/'.$slug;
        }

        return array_map(fn ($path) => [$path], $pages);
    }

    #[DataProvider('pages')]
    public function test_every_page_has_server_rendered_language_direction_and_metadata(string $path): void
    {
        foreach (['en' => '', 'ar' => 'ar/'] as $locale => $prefix) {
            $url = url('/'.$prefix.$path);
            $response = $this->get($url.'?source=test');
            $response->assertOk()->assertHeader('Content-Language', $locale)
                ->assertSee('<html lang="'.$locale.'" dir="'.($locale === 'ar' ? 'rtl' : 'ltr').'">', false)
                ->assertSee('<link rel="canonical" href="'.$url.'">', false)
                ->assertSee('hreflang="en" href="'.url('/'.$path).'"', false)
                ->assertSee('hreflang="ar" href="'.url('/ar/'.$path).'"', false)
                ->assertSee('hreflang="x-default" href="'.url('/'.$path).'"', false)
                ->assertSee('Concept Project')
                ->assertDontSee('wire:navigate');
            $this->assertDoesNotMatchRegularExpression('/>site\.[a-z_]+</', $response->getContent());
            $this->assertStringStartsWith('<!DOCTYPE html>', ltrim($response->getContent()));
            $this->assertLessThan(
                strpos($response->getContent(), 'rel="stylesheet"'),
                strpos($response->getContent(), "const key = 'lumadent-theme'"),
            );
        }
    }

    public function test_language_switch_keeps_the_service_and_renders_translated_content(): void
    {
        $this->get('/ar/services/routine-examinations')->assertOk()
            ->assertSee('الفحوصات الدورية')
            ->assertSee('href="'.url('/services/routine-examinations').'" lang="en"', false)
            ->assertSee('href="'.url('/ar/book').'"', false)
            ->assertDontSee('Routine examinations');
        $this->get('/ar/dentists')->assertSee('د. أميليا هارت')->assertSee('ملف تعريفي نموذجي');
        $this->get('/ar/contact')->assertSee('من الاثنين إلى الجمعة')->assertSee('مغلق');
    }

    #[DataProvider('pages')]
    public function test_default_prefixed_pages_redirect_permanently(string $path): void
    {
        $this->get('/en/'.$path.'?source=test')->assertStatus(301)
            ->assertRedirect(url('/'.$path).'?source=test');
    }

    public function test_unknown_paths_have_localized_themed_404_pages(): void
    {
        foreach (['/ar/missing', '/ar/services/missing'] as $path) {
            $this->get($path)->assertNotFound()
                ->assertSee('<html lang="ar" dir="rtl">', false)
                ->assertSee('الصفحة غير موجودة')->assertSee('noindex, follow')
                ->assertDontSee('rel="canonical"', false);
        }
        foreach (['/fr/', '/missing', '/en/missing', '/services/missing'] as $path) {
            $this->get($path)->assertNotFound()->assertSee('Page not found');
        }
    }

    public function test_sitemap_contains_all_pages_and_reciprocal_alternates(): void
    {
        $response = $this->get('/sitemap.xml')->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml);
        $this->assertCount(28, $xml->url);
        $response->assertSee('<loc>'.url('/ar/services/dental-implants').'</loc>', false)
            ->assertSee('hreflang="x-default"', false)
            ->assertDontSee('/en/');
    }

    public function test_default_can_change_to_arabic_and_english_can_be_disabled(): void
    {
        config(['localization.default' => 'ar']);
        $this->reloadPublicRoutes();
        $this->get('/')->assertOk()->assertSee('<html lang="ar" dir="rtl">', false);
        $this->get('/en/services')->assertOk()->assertSee('Routine examinations');
        $this->get('/ar/services')->assertStatus(301)->assertRedirect(url('/services'));

        config(['localization.locales' => ['ar' => ['label' => 'العربية', 'direction' => 'rtl']]]);
        $this->reloadPublicRoutes();
        $this->get('/services')->assertOk()->assertDontSee('hreflang="en"', false);
        $this->get('/en/services')->assertNotFound();
    }

    public function test_translation_keys_match_and_booking_has_no_form(): void
    {
        $this->assertSame(array_keys(require lang_path('en/site.php')), array_keys(require lang_path('ar/site.php')));
        foreach (['/book', '/ar/book'] as $path) {
            $this->get($path)->assertOk()->assertDontSee('<form', false)->assertDontSee('<input', false);
        }
    }

    private function reloadPublicRoutes(): void
    {
        Route::setRoutes(new RouteCollection);
        require base_path('routes/web.php');
        Route::getRoutes()->refreshNameLookups();
        app('url')->setRoutes(Route::getRoutes());
    }
}

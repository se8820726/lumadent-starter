<?php

use App\Http\Controllers\DentistController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SitemapController;
use App\Http\Middleware\RedirectDefaultLocale;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

$pages = static function (): void {
    Route::get('/', HomeController::class)->name('home');
    Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
    Route::get('/services/{service}', [ServiceController::class, 'show'])->name('services.show');
    Route::get('/dentists', DentistController::class)->name('dentists.index');
    Route::view('/about', 'about')->name('about');
    Route::view('/contact', 'contact')->name('contact');
    Route::view('/privacy', 'privacy')->name('privacy');
    Route::view('/book', 'booking.demo')->name('booking.demo');
};

$default = config('localization.default');
$locales = config('localization.locales');

if (! isset($locales[$default])) {
    throw new LogicException('The default language must be an enabled locale.');
}

foreach (array_keys($locales) as $locale) {
    Route::prefix($locale === $default ? '' : $locale)
        ->name($locale === $default ? '' : $locale.'.')
        ->middleware(SetLocale::class.':'.$locale)
        ->group($pages);
}

Route::prefix($default)->name('default-alias.')
    ->middleware(RedirectDefaultLocale::class)->group($pages);

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

Route::fallback(function () use ($locales, $default) {
    $segment = request()->segment(1);
    app()->setLocale(isset($locales[$segment]) ? $segment : $default);
    abort(404);
});

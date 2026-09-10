# LumaDent Starter

A fictional London dental clinic concept built with Laravel, Blade, Tailwind CSS and Alpine.js. The current foundation includes responsive public pages, English and Arabic, and light, dark and automatic appearance.

The clinic, team and contact details are examples. Booking is currently an introductory demo page; it does not collect personal information or create appointments.

The repository uses the same two-directory layout as production:

```text
lumadent/     private Laravel application
public_html/  public web document root
```

## Local development

Requirements: PHP 8.3+, Composer, Node.js and npm.

Run application commands from `lumadent`.

1. Install PHP dependencies with `composer install`.
2. Copy `.env.example` to `.env`, generate the application key with `php artisan key:generate`, and configure a local SQLite database.
3. Run `php artisan migrate`.
4. Install front-end dependencies with `npm.cmd ci` on Windows.
5. Build assets with `npm.cmd run build`.
6. Start the local application with `php artisan serve`.

Use `npm` in place of `npm.cmd` outside Windows. Vite writes production assets into the sibling `public_html/build` directory. The production server does not need Node.js. Set `APP_URL` to the deployed HTTPS origin.

## Languages and URLs

Edit `config/localization.php`:

```php
return [
    'default' => 'en',
    'locales' => [
        'en' => ['label' => 'English', 'direction' => 'ltr'],
        'ar' => ['label' => 'العربية', 'direction' => 'rtl'],
    ],
];
```

The default language must be present in `locales`. With English as default, URLs are `/`, `/services`, `/ar/` and `/ar/services`. Default-prefixed aliases such as `/en/services` permanently redirect to `/services`. Service slugs stay the same in every language. Unsupported language paths return 404.

The language switch uses normal links to the equivalent page. Pages render their text, `lang`, `dir`, canonical and reciprocal hreflang metadata on the server. Language selection does not depend on cookies or JavaScript. The sitemap is available at `/sitemap.xml`.

To add a language, create its `lang/<locale>/site.php` interface dictionary and `lang/<locale>/content.php` content overrides, then enable its label and direction in the configuration. Finish translations and check both desktop and mobile layouts before enabling a language publicly. Missing content overrides fall back to the English PHP content configuration.

After changing languages or the default, refresh configuration and route caches during deployment. Changing the default on an already published site also changes its URLs; plan redirects for existing indexed pages.

## Content

- `config/clinic.php`: clinic details, example address, contact details and opening hours.
- `config/treatments.php`: English services and stable slugs.
- `config/dentists.php`: English sample team profiles.
- `lang/en/site.php` and `lang/ar/site.php`: interface and page copy.
- `lang/ar/content.php`: translated clinic, service and team fields.

Keep the ordered dentist translations aligned with the dentist configuration. No management panel is included in Starter.

## Appearance and layout

The header offers Light, Dark and Auto. Auto follows the device preference, including changes while the page is open. Only the appearance preference is stored under `lumadent-theme` in local storage. If storage is blocked, changing appearance still works for the current page.

`resources/js/theme.js` is included synchronously in the document head before the blocking production stylesheet. It applies the saved appearance before page content is painted. System fonts avoid delayed font substitution. With JavaScript unavailable, CSS follows the system appearance and the navigation remains usable.

Build production assets before evaluating first-load appearance. The Vite development server is for editing, not production delivery. If a Content Security Policy is added, authorise the inline theme script with a matching nonce or hash; do not disable the policy. Recheck first paint after changing asset delivery or adding web fonts.

Layout uses logical properties for RTL/LTR, responsive grids, native mobile navigation, visible keyboard focus and reduced-motion support.

## Verification

- `php artisan test`: content, public routes, language configuration, metadata, redirects and sitemap.
- `npm.cmd test`: initial appearance, persistence, live system preference, blocked storage and history restoration.
- `npm.cmd run build`: production assets.

Browser verification should cover English and Arabic, both colour themes, 320px/mobile/tablet/desktop layouts, language switching on service details, reloads and Back/Forward navigation.

## Production deployment

The repository includes `.github/workflows/deploy.yml`. A push to `main` runs commands from `lumadent`, builds Vite assets in `public_html`, packages both directories and sends the release to the standalone PHP deployer through a short-lived GitHub artifact URL. The production server needs PHP 8.3 and outbound HTTPS but does not need Git, Composer, Node.js, SSH or FTP.

Only the newest production workflow continues running. The deployer updates the private `lumadent` directory and public `public_html` directory, checks the configured URLs and restores the previous retained package when its own health checks fail. GitHub then checks the public English and Arabic URLs independently. A failure in this external check fails the job without requesting rollback.

Create a GitHub Environment named `production` with:

- secrets `DEPLOY_ENDPOINT` and `DEPLOY_SECRET`;
- variable `HEALTH_URLS` containing the public checks as JSON;
- optional variables `DEPLOY_TIMEOUT_SECONDS` and `DEPLOY_POLL_SECONDS`.

The Docker and shared-host runtime, first-install steps and recovery states are documented in the separate `lumadent-starter-docker` project. Keep the production Laravel `.env` only on the server under shared configuration; release archives never contain it.

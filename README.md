# LumaDent Starter

A fictional London dental clinic concept built with Laravel, Blade, Tailwind CSS and Alpine.js. The current foundation includes responsive public pages, English and Arabic, and light, dark and automatic appearance.

The clinic, team and contact details are examples. Booking is currently an introductory demo page; it does not collect personal information or create appointments.

The repository uses the same two-directory layout as production:

```text
lumadent/     private Laravel application
public_html/  public web document root
```

## Local development

Requirements: PHP 8.4.1 or newer, Composer, Node.js 24 and npm.

Run application commands from `lumadent`.

1. Install PHP dependencies with `composer install`.
2. Copy `.env.example` to `.env` and generate the application key with `php artisan key:generate`.
3. Because `.env.example` is the production template, set `APP_ENV=local`, `APP_DEBUG=true`, `APP_URL=http://127.0.0.1:8000`, `DB_CONNECTION=sqlite`, `SESSION_DRIVER=file`, `SESSION_SECURE_COOKIE=false` and `CACHE_STORE=file` in the local `.env`. Remove the PostgreSQL-only `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` and `DB_SSLMODE` entries.
4. Ensure `database/database.sqlite` exists, then run `php artisan migrate`.
5. Install front-end dependencies with `npm.cmd ci` on Windows.
6. Build assets with `npm.cmd run build`.
7. Start the local application with `php artisan serve`.

Use `npm` in place of `npm.cmd` outside Windows. Vite writes production assets into the sibling `public_html/build` directory. The production server does not need Node.js. Set `APP_URL` to the deployed HTTPS origin.

The Laravel `public` filesystem disk writes directly to `public_html/uploads` and produces URLs under `/uploads`. No `storage:link` command or symbolic link is required. Treat every file in that directory as publicly readable; sensitive files must use a private disk.

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

After changing languages or the default, refresh any configuration and route caches used by the server after deployment. Changing the default on an already published site also changes its URLs; plan redirects for existing indexed pages.

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

The repository includes `.github/workflows/deploy.yml`. A push to `main` tests the application with PHP 8.5 and Node.js 24, builds Vite assets in `public_html`, installs production PHP dependencies and packages the `lumadent` and `public_html` directories. GitHub then sends a signed JSON request containing a short-lived artifact URL to the deployment endpoint.

### Shared-host requirements

Use PHP 8.5 with the standard Laravel extensions plus `bcmath`, `fileinfo`, `intl`, `mbstring`, `openssl`, `pdo_pgsql`, `redis` and `zip`. Set `allow_url_fopen=On` so PHP can download the HTTPS artifact, and permit outbound HTTPS connections. The deployment request is synchronous, so `max_execution_time` should be at least as long as `DEPLOY_TIMEOUT_SECONDS`; 600 seconds is the workflow default. OPcache is recommended for production performance but is not required by the deployer.

The web-server process needs read and write access to `deployer`, `lumadent` and `public_html`. Git, Composer and Node.js are not required on the production server. A hosting terminal or SSH is needed only for initial setup, migrations and other manual maintenance.

### One-time shared-host bootstrap

Create this structure above the public document root before the first deployment:

```text
account-root/
├── deployer/
│   ├── deploy.php
│   └── SharedHostDeployer.php
├── lumadent/
│   ├── .env
│   └── storage/
└── public_html/
    ├── deploy.php
    └── uploads/
```

Upload the bootstrap files from this repository without changing their relative locations:

- `deployer/deploy.php`
- `deployer/SharedHostDeployer.php`
- `public_html/deploy.php`

Create the private `lumadent/.env` from `lumadent/.env.example`, set `APP_URL=https://lumadent.prodaynews.com`, configure the production PostgreSQL and Redis connections, and set this health target:

```dotenv
DEPLOY_HEALTH_URLS='[{"url":"https://lumadent.prodaynews.com/up","status":[200],"marker":""}]'
```

Generate the Laravel application key and deployment secret on Linux:

```sh
php -r 'echo "base64:".base64_encode(random_bytes(32)).PHP_EOL;'
openssl rand -hex 32
```

Put the first output in `lumadent/.env` as `APP_KEY`. Put the second output there as `DEPLOY_SECRET`. The deployment secret must contain at least 32 bytes and must exactly match the GitHub secret.

### GitHub production environment

Create a GitHub Environment named `production` with these secrets:

- `DEPLOY_ENDPOINT=https://lumadent.prodaynews.com/deploy.php`
- `DEPLOY_SECRET` with the exact value stored in the server's `lumadent/.env`

The optional environment variable `DEPLOY_TIMEOUT_SECONDS` defaults to `600` and accepts values from 30 to 3600 seconds.

After the bootstrap files and server environment are ready, run the workflow manually or push to `main` to perform the first deployment.

### Deployment and rollback behavior

The endpoint accepts only POST requests. It verifies the shared HMAC signature, downloads the artifact, extracts the inner release ZIP, verifies its complete SHA-256 checksum, creates `deployer/rollback.zip`, overlays the release and runs the health checks from `DEPLOY_HEALTH_URLS`.

Any failure from the start of extraction through the final health check removes the new release files and restores the file backup. If rollback itself fails, the error is appended to `deployer/deploy.log`. Runtime downloads, the rollback archive, the deployment lock and the log stay in `deployer`, which is outside every release package.

Releases do not contain or replace `deployer`, `lumadent/.env`, `public_html/deploy.php` or `public_html/uploads`. Existing data under `lumadent/storage` is retained by the overlay deployment. Public uploads must be stored in `public_html/uploads`; no storage symlink is used.

Each health target needs an HTTPS URL, a non-empty list of accepted status codes and a response marker. An empty marker checks only the status. The web process must have enough execution time to finish the download, complete backup, extraction and health checks in the same request.

The deployment signature covers the artifact URL, complete release SHA-256 and a random salt. The secret never appears in the URL or request. Replay history is intentionally not stored; HTTPS and the short-lived artifact URL limit the accepted replay window.

### Database migrations

The deployer does not run Artisan commands. After the first successful deployment, enter the private Laravel directory and run:

```sh
cd /path/to/lumadent
php artisan migrate --force --no-interaction
```

Run the same migration command after any later release that contains new migrations. File rollback does not reverse database changes. Production migrations must therefore remain backward-compatible with both the previous and current application release; take PostgreSQL backups separately before risky schema or data changes.

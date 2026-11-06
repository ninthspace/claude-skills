# Deployment stamp on non-production environments

Every application this skill provisions shows a deployment stamp on each environment that is not `production`: staging, previews, and any other. It is a small fixed badge on every customer page and every admin panel page, reading `{environment} · {7-char commit} · built {date time}`, with the time in the application's display timezone. Production and local development show nothing.

## How to build it

- Read `LARAVEL_CLOUD_ENV_NAME` and `LARAVEL_CLOUD_COMMIT_SHA` at render time with `Illuminate\Support\Env::get()`. Never read them through a config entry: Cloud sets them only at runtime, and the build's `php artisan optimize` caches config, so a config entry holds null and the stamp never appears. Tests that set the variables before the app boots do not catch this; set them after boot (`putenv`, `$_ENV`, `$_SERVER`) and clear them in `afterEach`.
- Cloud gives no build time. Use the modification time of the cached config file (`app()->getCachedConfigPath()`), which the build writes. When the file is missing, leave the time out.
- Render nothing when the environment name is empty or `production`.
- Render it once per surface: in the shared customer layout, and in a Filament panel through a `PanelsRenderHook::BODY_END` render hook. Style it from the project's brand tokens, in one stylesheet imported by both the customer entry and the admin theme, with `pointer-events: none` so it never blocks a click.

## How to check it

After the first deploy of an environment, fetch a public page on it (for example the sign-in page) and confirm the stamp shows the commit just deployed. Fetch `production` as well and confirm it shows none.

Reference implementation: hlh-splash `app/Support/DeploymentStamp.php`, `app/View/Components/DeploymentStamp.php`, `resources/css/deployment-stamp.css`, `tests/Feature/DeploymentStampTest.php` and `docs/quick/2026-11-06-deployment-stamp.md`.

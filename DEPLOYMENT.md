# Deploying to Laravel Cloud

This guide covers the first deployment of ElectionReport to [Laravel Cloud](https://cloud.laravel.com/), the settings the app depends on, and how to prepare for election-day traffic. Facts about Laravel Cloud come from its [documentation](https://laravel.com/cloud/docs/llms.txt), checked in September 2026. Plans and sizes change, so confirm limits in the dashboard before you rely on them.

## What the app needs

| Need                         | Laravel Cloud resource                | Why                                                                                                                                                     |
| ---------------------------- | ------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Database                     | Laravel Serverless Postgres           | Laravel Cloud does not support SQLite. Postgres includes a connection pooler that allows up to 10,000 connections, which many autoscaled replicas need. |
| Cache, sessions, rate limits | Laravel Valkey                        | Keeps the results cache, sessions and per-IP rate limits off the database.                                                                              |
| Compute                      | App cluster, Pro class in production  | Pro sizes are always on and built for sustained load.                                                                                                   |
| Inertia SSR                  | "Use Inertia SSR" on the App cluster  | `config/inertia.php` has SSR enabled, so the app expects an SSR server.                                                                                 |
| Queue workers, scheduler     | None                                  | The app has no queued jobs or scheduled tasks.                                                                                                          |
| Object storage               | Laravel Object Storage, public bucket | Result sheet photos that voters upload. The app filesystem is wiped on every deploy and not shared between replicas, so photos cannot live on it.       |

## Before the first deploy

1. **Push the code to GitHub, GitLab or Bitbucket.** Laravel Cloud deploys from a Git provider. Check that these files are committed, since the build and seeders need them:
    - `database/data/locations.json.gz` (the INEC polling unit register)
    - `resources/js/data/nigeria-states.json` (the results map)
    - `public/images/parties/*.png` (party logos, once you have them)
2. **Generate the hash key once and store it somewhere safe**, such as your password manager:

    ```bash
    openssl rand -hex 32
    ```

    The app uses `ELECTIONREPORT_HASH_KEY` to hash every VIN and IP address. If the key changes or is lost, the app can no longer recognise VINs that already reported, and every voter could report again. Never rotate it during an election.

3. **Create a Turnstile widget** for your domains. See [Cloudflare Turnstile](README.md#cloudflare-turnstile) in the README.
4. **Run the test suite against Postgres at least once.** CI and local tests use SQLite. The dashboard's raw SQL is written to be portable, but confirm it on the real engine before election day.

## Create the application

1. In the Laravel Cloud dashboard, create an application from the repository. Pick the region closest to Nigeria that Laravel Cloud offers, and keep the database and cache in the same region.
2. Create two environments:
    - **staging**, for load tests and demo data. Flex compute with scale-to-zero keeps it cheap.
    - **production**, for the public site.
3. For each environment, attach:
    - a **Laravel Serverless Postgres** database,
    - a **Laravel Valkey** cache, with the eviction policy set to `allkeys-lru`,
    - a **Laravel Object Storage** bucket for result sheet photos. See [Result sheet storage](#result-sheet-storage) for its settings.

    Attaching a resource injects its connection variables (`DB_HOST`, `DB_USERNAME`, `DB_PASSWORD`, `DB_DATABASE`, `REDIS_HOST`, `REDIS_PASSWORD`, and the bucket's `AWS_*` credentials) and takes effect on the next deploy.

## Build and deploy commands

Set these on each environment.

Build command:

```bash
composer install --no-dev --optimize-autoloader && npm ci && npm run build:ssr && php artisan optimize
```

Deploy command:

```bash
php artisan migrate --force
```

Laravel Cloud loads the environment's variables into a `.env` file during the build, so `php artisan optimize` caches the real production config. Cache config in the build, never in the deploy command. Files the deploy command writes are thrown away. Build and deploy commands each time out after 15 minutes.

Do not add `queue:restart`, `optimize:clear` or `storage:link` to the deploy command. Laravel Cloud handles process restarts, and the filesystem is not persistent.

## Turn on Inertia SSR

Edit the environment's App cluster, turn on **Use Inertia SSR**, save, and deploy. The `build:ssr` build command above produces the server bundle this setting runs.

## Result sheet storage

Voters can upload a photo of their polling unit's result sheet (Form EC8A). Anyone can view these photos, so they go in a public bucket.

1. On the environment's infrastructure canvas, click **Add bucket**, choose to create a new bucket, and select **Laravel Object Storage**.
2. Fill in the bucket settings:
    - **Name:** `electionreport-result-sheets-production`, or `-staging` for staging. Give each environment its own bucket, so load-test photos never mix with real ones.
    - **Visibility:** Public. Visibility applies to the whole bucket and is the only visibility setting that matters. Laravel Cloud buckets run on Cloudflare R2, which rejects per-file visibility, so the app never sets it.
    - **Disk name:** `public`. The app stores photos on the disk named in `ELECTIONREPORT_RESULT_SHEET_DISK`, which defaults to `public`, so this name needs no extra variable.
    - **Default disk:** leave this off. The app has no other files to store.
    - **Jurisdiction:** Automatic. **Location hint:** Western Europe, the option closest to Nigeria. Neither can change after creation.
3. Redeploy the environment so the bucket's credentials take effect.
4. Check the setup in live mode, on staging or before the election opens, because test mode never uploads photos. Report from a polling unit, upload a result sheet photo, and open it from the result sheet page. The photo's address should be the bucket's public URL, shown on the bucket's settings page. If it starts with your `APP_URL` followed by `/storage`, the bucket is not attached with the disk name `public`.

Photos are stored once, never changed, and sent with a one-year cache header, so a browser that has shown a photo does not download it again. In test mode the photo never leaves the voter's phone: the app saves a built-in sample sheet in its place, so practice can't fill the bucket and result sheets work in test mode without a bucket at all. Live photos go under a `live/` prefix. Photos from before this change are under `test/`, and you can delete that folder, for example with Cyberduck as the Laravel Cloud object storage docs describe, without touching anything live.

## Environment variables

Set these on each environment. Variables you set override the ones Laravel Cloud injects.

```dotenv
APP_NAME=ElectionReport
APP_ENV=production
APP_DEBUG=false
APP_URL=https://electionreport.ng

ELECTIONREPORT_MODE=test
ELECTIONREPORT_FEEDBACK_URL=https://forms.example.com/electionreport-feedback

CACHE_STORE=redis
SESSION_DRIVER=redis

# Use the pooled Postgres host: copy the injected DB_HOST and add -pooler to its first part.
DB_CONNECTION=pgsql
DB_HOST=your-database-host-pooler

TURNSTILE_SITE_KEY=your-site-key
```

On staging, set `APP_ENV=staging` and `APP_URL` to its `laravel.cloud` domain, and use Cloudflare's always-pass Turnstile test keys so load tests can get through the VIN step.

Check that `APP_KEY` is set on each environment. If it is missing, generate one locally with `php artisan key:generate --show` and add it.

Store the two values that must never leak in **Secrets Manager**, then attach them to each environment:

- `ELECTIONREPORT_HASH_KEY`
- `TURNSTILE_SECRET_KEY`

Secret values cannot be read back after creation, which is why the hash key needs a copy in your password manager as well.

Redeploy after any change to variables, secrets or attached resources. Changes stay staged until the next deploy.

## First deploy and data load

1. Deploy the environment and wait for it to finish. Laravel Cloud swaps in the new release with zero downtime.
2. Load the parties, the election and INEC's polling unit register. Open the environment's **Commands** tab and run:

    ```bash
    php artisan db:seed --force
    ```

    This imports 176,623 polling units. The import needs about 150MB of memory and raises its own PHP memory limit to 512MB, so run it on an instance with at least 1GB of memory. Commands can run for up to 30 minutes. The seeders update existing rows, so running it again is safe.

3. The election seeder opens the 2027 presidential election for reports. While production runs in test mode, leave it open so testers can report. Once you switch to live mode, close it until election day:

    ```bash
    php artisan tinker --execute 'App\Models\Election::query()->update(["is_accepting_reports" => false]);'
    ```

    Run the same command with `true` on election morning to open reporting.

4. On staging only, add demo reports to check the dashboard. The seeder refuses to run when `APP_ENV=production`:

    ```bash
    php artisan db:seed --class=DemoReportSeeder --force
    ```

## Public testing before the election

`ELECTIONREPORT_MODE` decides whether the site is a practice run or the real thing.

- `test` shows a TEST badge beside the logo and a banner on every page saying the data is practice data. Reports go to the `test_reports` table, and the results page shows only those. If `ELECTIONREPORT_FEEDBACK_URL` is set, the banner links to it so testers can send feedback.
- `live` hides the badge and banner, saves reports to the `reports` table, and shows only live results.

Any value other than `live`, including a typo or a missing variable, counts as `test`. A mistake then shows a visible TEST badge instead of mixing practice reports into the real count.

Test and live reports are separate for duplicate checks too. A VIN used while testing can still report once the site goes live.

To switch production to live, set `ELECTIONREPORT_MODE=live` and redeploy. The results cache is keyed by mode, so the dashboard changes over straight away. Test reports stay in `test_reports` until you remove them:

```bash
php artisan tinker --execute 'DB::table("test_reports")->truncate();'
```

## Check that rate limits see real visitor IPs

The per-IP rate limits and the stored IP hashes only work if `$request->ip()` returns the visitor's address. All traffic reaches the app through Laravel Cloud's Cloudflare edge, and the Laravel Cloud docs do not say whether trusted proxies are configured for you. If they are not, every visitor appears to come from a handful of edge addresses. The rate limits would then treat thousands of voters as one visitor and start returning 429 errors.

Test this on staging before launch:

1. On a phone using mobile data, submit the VIN form 11 times in a minute. The 11th attempt should fail with "Too Many Requests".
2. Straight away, submit the VIN form from a laptop on a different network. It should succeed.

If the laptop is also blocked, the app sees the edge address instead of the visitor. Add `$middleware->trustProxies(at: '*');` to the `withMiddleware` block in `bootstrap/app.php`, deploy, and repeat the test. Trusting every proxy is acceptable here only because the app runs on a private network that the public cannot reach except through Laravel Cloud's edge.

## Custom domain

1. In the production environment's network settings, click **Add domain** and add `electionreport.ng`. Choose to redirect `www.electionreport.ng` to it.
2. Add the DNS records Laravel Cloud shows: an A record (or a CNAME if the domain uses Cloudflare DNS), a `_cf-custom-hostname` TXT record for ownership, and an `_acme-challenge` record for the certificate. Most domains connect within 15 minutes, and Laravel Cloud issues the certificate automatically.
3. Add both hostnames to the Turnstile widget, and set `APP_URL` to the custom domain. Redeploy.

Laravel Cloud's own `*.laravel.cloud` domains send `X-Robots-Tag: noindex`, so search engines only index the custom domain.

## Sizing for election day

Most visitors arrive in a few hours on election day, so size for that peak rather than for normal traffic.

- **Plan.** Autoscaling needs the Growth plan or higher. Growth allows up to 10 replicas and keeps logs for 7 days. Business allows 20 replicas and keeps logs for 30 days, which covers post-election disputes.
- **Compute.** Use a Pro size in production with scale-to-zero off. Set autoscaling to Custom, with a minimum of 2 replicas and a maximum at your plan's limit. Each replica runs about one PHP worker per 30MB of memory, so a 2GB replica handles roughly 66 requests at once.
- **Database.** Set the Postgres compute range so its minimum is well above idle for election week, and make sure it does not scale to zero. Changing the range restarts the database compute and briefly drops connections, so do it days before the election, not during it. Set point-in-time recovery to at least 7 days.
- **Cache.** The smallest Valkey size (250MB) is enough. Sessions, rate-limit counters and the results cache all expire on their own.

The results dashboard caches its numbers for 60 seconds, so heavy traffic on `/results` costs a few database queries per minute per area, not per visitor. Laravel Cloud's edge cannot cache the dashboard pages themselves, because every response sets a session cookie.

Leave Laravel Cloud's edge rate limiting off, or set it far above 100 requests a minute. Nigerian mobile carriers put many phones behind one IP address, so a strict per-IP edge limit would block real voters at busy polling units.

## Load test on staging

Run a load test against staging at least a week before the election, with the same compute and database settings as production.

1. Seed staging with `DemoReportSeeder` so the dashboard has data to aggregate.
2. Use a load-testing tool such as [k6](https://k6.io/) to replay the voter flow: `GET /`, `POST /vin`, `GET /report`, the three location lookups, and `POST /reports`. Mix in `GET /results` traffic. Staging's always-pass Turnstile keys let scripted requests through the VIN step.
3. Spread the test across several source IPs, or raise the `reports` and `vin` limits in `AppServiceProvider` for the test. Otherwise the per-IP rate limits cap it.
4. Watch the replica count, the Postgres compute units, and response times in the dashboard. Raise the production minimums until the peak you expect runs without errors.

## Election-day runbook

A week before:

- Set `ELECTIONREPORT_MODE=live` on production.
- Raise the production replica minimum and the Postgres compute range to election-day levels, and redeploy.
- Check that the TEST badge is gone and that `/results` shows no reports.
- Confirm the `trustProxies` check above passed on the current release.
- Take a manual database backup.

On the day:

- Open reporting with the tinker command above and check that `/` shows the VIN form.
- Keep the **Logs** tab open. `Turnstile verification unreachable` warnings mean Cloudflare was down and VINs went through unchecked. A burst of `Turnstile verification rejected` lines usually means bots are trying the form.
- If a flood of bot traffic gets past Turnstile, switch on Laravel Cloud's **Under Attack Mode**. It adds a browser check for every visitor and turns itself off after 24 hours.

Afterwards:

- Close reporting with the tinker command.
- Take a database backup and keep it with the hash key. The two together are the full record of what voters reported.
- Lower the replica minimum and database range.

## Useful commands

Laravel Cloud's CLI can do most of this from a terminal. It is not a project dependency. Install it globally with `composer global require laravel/cloud-cli`, sign in with `cloud auth`, and check each command's options with `cloud <command> -h`.

```bash
cloud deploy electionreport production -n       # deploy
cloud deploy:monitor -n                        # follow the deployment
cloud command:run production --cmd='php artisan db:seed --force' -n
cloud tinker production --code='echo App\Models\Report::count();' -n
```

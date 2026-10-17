<p align="center">
  <img src="public/images/brand/electionreport-logo-wide.png" alt="ElectionReport" width="480">
</p>

# ElectionReport

ElectionReport lets Nigerian voters report what happened at their polling unit on election day. A voter enters the first 4 and last 4 characters of their VIN, picks their polling unit, and says whether the election held, whether they voted, and who they voted for.

## Local setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Set `ELECTIONREPORT_HASH_KEY` in `.env` to a long random string, for example the output of `openssl rand -hex 32`. The app uses it to hash VINs and IP addresses. Then create the database and start the app:

```bash
php artisan migrate --seed
php artisan storage:link
composer run dev
```

`storage:link` lets the browser load result sheet photos, which are stored on the `public` disk locally. In production, set `ELECTIONREPORT_RESULT_SHEET_DISK` to a public bucket disk.

The seeder loads the 2023 presidential parties, a 2027 presidential election that is open for reports, and INEC's full register of 176,623 polling units from `database/data/locations.json.gz`.

## Deployment

See [DEPLOYMENT.md](DEPLOYMENT.md) for deploying to Laravel Cloud, sizing for election day, and the election-day runbook.

## Cloudflare Turnstile

The VIN step is protected by [Cloudflare Turnstile](https://developers.cloudflare.com/turnstile/), a CAPTCHA alternative that most visitors pass without clicking anything. The browser gets a token from Cloudflare, and the server checks that token with Cloudflare before it accepts the VIN.

### Local development

`.env.example` ships with Cloudflare's test keys, which always pass and work on any hostname:

```dotenv
TURNSTILE_SITE_KEY=1x00000000000000000000AA
TURNSTILE_SECRET_KEY=1x0000000000000000000000000000000AA
```

The widget shows a "For testing only" banner with these keys. To try the failure path, swap in the secret key that always fails, `2x0000000000000000000000000000000AA`. Cloudflare lists all test keys in its [testing docs](https://developers.cloudflare.com/turnstile/troubleshooting/testing/).

### Production keys

1. Sign in to the [Cloudflare dashboard](https://dash.cloudflare.com/) and open **Turnstile**. A free Cloudflare account is enough, and the domain does not need to use Cloudflare DNS.
2. Click **Add widget**. Name it `ElectionReport`.
3. Under **Hostnames**, add every domain the app is served from: `electionreport.ng`, `www.electionreport.ng`, and the `*.laravel.cloud` domain Laravel Cloud gives your environment. The widget refuses to load on any hostname not in this list.
4. Set **Widget mode** to **Managed**. Cloudflare then shows a checkbox only to visitors it considers suspicious.
5. Create the widget and copy the **Site Key** and **Secret Key**.

### Laravel Cloud

1. Open the application in Laravel Cloud, choose the production environment, and go to its environment variables.
2. Add both keys:

    ```dotenv
    TURNSTILE_SITE_KEY=your-site-key
    TURNSTILE_SECRET_KEY=your-secret-key
    ```

3. Redeploy the environment. Laravel Cloud caches config during deploy, so the new values only apply after a deploy.
4. Open the site, enter a VIN, and check that the widget loads without the "For testing only" banner and that **Continue** takes you to the polling unit step.

Treat the secret key like a password. It belongs only in `.env` and Laravel Cloud's environment variables, never in git or frontend code. The site key is public, since the page sends it to the browser.

### How verification behaves

- The **Continue** button stays disabled until Turnstile issues a token.
- The server rejects the VIN if the token is missing or Cloudflare rejects it. The voter then sees "The security check failed. Please try again." and the widget issues a fresh token, because each token works only once.
- If the server cannot reach Cloudflare, or Cloudflare returns a 5xx error, the server accepts the VIN and logs a warning. An outage on election day should not lock every voter out. The duplicate-VIN check and the per-IP rate limits still apply.
- The server logs rejected tokens with Cloudflare's error codes at `info` level. A spike in these logs points to bot traffic, and `invalid-input-secret` means the secret key is wrong.
- If `TURNSTILE_SITE_KEY` is empty, the widget does not render and no VIN can be submitted, so a missing key shows up as soon as someone tries the form.

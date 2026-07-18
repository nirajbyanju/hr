# Production Hardening Checklist — SamriddhiHR

Run through this before any deployment that faces real users. It addresses audit
finding **SEC-04** (debug mode / insecure defaults) plus the surrounding
production concerns.

## Environment (`.env`)

| Key | Dev value | **Production value** | Why |
|-----|-----------|----------------------|-----|
| `APP_ENV` | `local` | `production` | Enables framework production optimizations |
| `APP_DEBUG` | `true` | **`false`** | Prevents stack traces / queries / env leaking on error pages |
| `APP_URL` | `http://localhost` | `https://your-domain` | Correct absolute URLs, secure cookies |
| `SESSION_ENCRYPT` | `false` | **`true`** | Encrypts session payloads at rest |
| `SESSION_SECURE_COOKIE` | (unset) | **`true`** | Cookies only sent over HTTPS |
| `SESSION_SAME_SITE` | `lax` | `lax` (or `strict`) | CSRF hardening |
| `LOG_LEVEL` | `debug` | `warning` | Avoids verbose/sensitive logging in prod |
| `DB_CONNECTION` | `sqlite` | `mysql` / `pgsql` | See audit **DB-01** — SQLite is not for concurrent load |
| `MAIL_MAILER` | `log` | real transport (`smtp`, …) | See audit **BIZ-01** — notifications must actually send |
| `CACHE_STORE` / `QUEUE_CONNECTION` | `database` | `redis` (recommended) | See audit **PERF-01** — real queue + cache backend |

## Deploy-time commands

```bash
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
php artisan storage:link
```

> Re-run `php artisan config:clear` before changing `.env`, then re-cache.

## Web server / TLS

- [ ] Serve over HTTPS only; redirect HTTP → HTTPS.
- [ ] Document root points at `public/` (never expose the project root).
- [ ] Security headers: `Strict-Transport-Security`, `X-Content-Type-Options: nosniff`,
      `X-Frame-Options: SAMEORIGIN`, and a `Content-Security-Policy`.
- [ ] Ensure `.env`, `storage/`, and `vendor/` are not web-accessible.

## Runtime

- [ ] A queue worker is running (`php artisan queue:work`, supervised) once jobs are added (PERF-01).
- [ ] Scheduler cron is registered (`php artisan schedule:run` every minute).
- [ ] Automated database backups are configured (not possible with a single SQLite file at scale).

## Secrets

- [ ] `APP_KEY` is set and unique per environment.
- [ ] No real credentials committed. `.env` stays git-ignored (already the case).
- [ ] Rotate any keys that were ever shared in a dev/demo `.env`.

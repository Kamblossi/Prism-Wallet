# Deploying Prism Wallet to Production

This app is a PHP-FPM + Nginx application with background cron work and file uploads. It connects to a PostgreSQL database. These characteristics make Docker-friendly platforms a great fit.

If you want a managed database, Supabase works well (it's standard Postgres).

## TL;DR checklist

- Set up a managed Postgres (e.g., Supabase). Copy its connection string.
- Choose a Docker PaaS (Render/Railway/Fly/DO App Platform). Build from this repo's Dockerfile.
- Set environment variables from `.env.example` (at minimum DB creds, APP_URL).
- Mount a persistent volume for `images/uploads/logos` (user-uploaded logos and avatars).
- Ensure port 80 is exposed by the container (already done in Dockerfile) and healthcheck hits `/health.php`.
- After first boot, visit `/endpoints/db/migrate.php` or let the startup script run it to initialize/update schema.
- Point your domain to the app URL via DNS and set `APP_URL` accordingly.

## Why not Vercel (serverless)?

Vercel’s PHP support is designed for serverless functions. This app:
- Requires Nginx + PHP-FPM running together
- Uses cron/background tasks and writes to local disk for uploads
- Expects a stable filesystem and long-running process

Those conflict with serverless constraints. If you love Vercel for frontend, you could still host the PHP app elsewhere and front it via Vercel proxy, but simplest is a Docker PaaS.

## Option A: Render (recommended for simplicity)

1. Create a new Web Service
   - Build from GitHub repository
   - Runtime: Docker, auto-detects `Dockerfile`
   - Instance: choose a plan
   - Port: 80 (Render will auto-detect from healthcheck)

2. Environment variables (add from `.env.example`)
   - `APP_URL=https://prism-wallet.app`
   - Either provide `DATABASE_URL` from Supabase (preferred) or fields `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`
   - Set `DB_SSLMODE=require` for Supabase
   - Set `DB_DISABLE_HOSTADDR=1` to skip Docker network hostaddr logic
   - `TZ=Africa/Nairobi` (or your timezone)

3. Persistent storage
   - Add a Disk and mount at `/var/www/html/images/uploads/logos`

4. Healthcheck
   - Path: `/health.php`

5. Deploy. First boot will run DB migrations automatically from `startup.sh`.

## Option B: DigitalOcean App Platform

- Create app from repo
- Select Dockerfile
- Add Environment Variables as above
- Add a Persistent Volume and mount at `/var/www/html/images/uploads/logos`
- Healthcheck to `/health.php`

## Database: Supabase setup

1. Create a new Supabase project (Postgres)
2. Get the connection string
   - e.g. `postgres://USER:PASSWORD@HOST:6543/DBNAME?sslmode=require`
3. Paste as `DATABASE_URL` in the PaaS env settings
4. Ensure `DB_SSLMODE=require`

Schema will be created/updated by `endpoints/db/migrate.php` and guarded by `includes/connect.php` on first request. No special Postgres extensions are required.

## Domain and TLS

- Point `prism-wallet.app` to your PaaS service (CNAME or A record depending on platform)
- Enable HTTPS (most PaaS platforms auto-provision Let’s Encrypt)
- Set `APP_URL` to the https URL

## Initial admin configuration

- Visit `/admin/settings.php` to set SMTP, server URL, and registration options
- Registration email requires SMTP settings in DB (`admin` table) or via UI

## Environment variables reference

See `.env.example`. Key ones:
- `DATABASE_URL` or `DB_HOST/DB_PORT/DB_NAME/DB_USER/DB_PASSWORD`
- `DB_SSLMODE` (require on Supabase)
- `DB_DISABLE_HOSTADDR=1` when not using Docker internal DNS
- `APP_URL`
- `AUTH_PROVIDER=local`

## Notes

- Cron tasks are started in `startup.sh`. Adjust `cronjobs` entries as needed.
- If you scale horizontally, ensure the upload volume is shared or use object storage.
- Nginx listens on port 80 per `nginx.conf`. The platform will terminate TLS at the edge in most cases.
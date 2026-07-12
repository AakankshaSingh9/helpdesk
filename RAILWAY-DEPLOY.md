# Deploying to Railway

This app deploys as **one Railway service** built from the repo-root
[`Dockerfile`](./Dockerfile): it builds the Vue SPA and serves it from the same
Laravel container that runs the API. One origin means Sanctum's cookie auth is
first-party — no CORS, no cross-site-cookie headaches — so all you add is a
Postgres database and a handful of env vars.

> **Scope.** This is a demo-grade single-process setup (`php artisan serve`,
> `QUEUE_CONNECTION=sync`, database-backed cache/session, no Redis worker). The
> AI classify/auto-resolve jobs run inline on intake. Fine for low volume; swap
> in php-fpm + nginx and a real queue worker for production traffic.

---

## 1. Create the project

1. Push this branch to GitHub (see the bottom of this file if you haven't).
2. Railway → **New Project → Deploy from GitHub repo** → pick this repo/branch.
   Railway detects [`railway.json`](./railway.json) + the root `Dockerfile` and
   starts a build. The **first deploy will fail** (no database/env yet) — that's
   expected; keep going.

## 2. Add Postgres

**New → Database → Add PostgreSQL.** Railway's standard Postgres is enough — the
app needs no extensions today (pgvector is only for the future RAG phase). It
exposes a `DATABASE_URL` you'll reference below.

## 3. Set environment variables

On the **app service** → **Variables**, add the following. `${{Postgres.DATABASE_URL}}`
is a Railway [reference variable](https://docs.railway.com/guides/variables#reference-variables)
— it wires the app to the database over the private network.

```bash
# ── Core ──────────────────────────────────────────────────────────────────
APP_NAME=Helpdesk
APP_ENV=production
APP_DEBUG=false
APP_KEY=                       # generate — see below
LOG_CHANNEL=stderr             # Railway captures stdout/stderr as logs

# ── Database (Railway Postgres) ───────────────────────────────────────────
DB_CONNECTION=pgsql
DB_URL=${{Postgres.DATABASE_URL}}

# ── Single-process backend (no Redis/worker) ──────────────────────────────
QUEUE_CONNECTION=sync
CACHE_STORE=database
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true      # Railway serves HTTPS
SESSION_SAME_SITE=lax

# ── Seeded admin login (runs on every deploy; idempotent) ─────────────────
ADMIN_NAME=Aakanksha
ADMIN_EMAIL=you@example.com
ADMIN_PASSWORD=change-me-to-something-strong

# ── Mail: agent replies (Gmail SMTP) ──────────────────────────────────────
MAIL_MAILER=smtp                # or `log` to skip real delivery
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_SCHEME=null
MAIL_USERNAME=you@gmail.com
MAIL_PASSWORD=your-gmail-app-password   # 16-char App Password, 2FA required
MAIL_FROM_ADDRESS=you@gmail.com
HELPDESK_SUPPORT_ADDRESS=you@gmail.com
HELPDESK_AGENT_NAME=Aakanksha
MAIL_INBOUND_SECRET=            # set to enable the inbound-email webhook

# ── AI (optional — leave false to run as a plain helpdesk) ────────────────
AI_ENABLED=false
OPENAI_API_KEY=                 # Groq key (gsk_...) if you enable AI
OPENAI_BASE_URL=https://api.groq.com/openai/v1
OPENAI_MODEL=llama-3.3-70b-versatile

# ── Set these AFTER you generate a domain in step 4 ───────────────────────
APP_URL=https://REPLACE.up.railway.app
FRONTEND_URL=https://REPLACE.up.railway.app
SANCTUM_STATEFUL_DOMAINS=REPLACE.up.railway.app   # no scheme
```

**Generate `APP_KEY`** locally (it prints a `base64:...` string — paste it in):

```bash
docker compose exec api php artisan key:generate --show
```

## 4. Generate the public domain, then finish the auth vars

App service → **Settings → Networking → Generate Domain**. Copy the
`*.up.railway.app` hostname and set the three placeholders from step 3:

- `APP_URL` = `https://<domain>`
- `FRONTEND_URL` = `https://<domain>`
- `SANCTUM_STATEFUL_DOMAINS` = `<domain>` (host only, no `https://`)

Saving variables triggers a redeploy. (Using a custom domain later? Point these
at that hostname instead.)

## 5. Verify

- `https://<domain>/up` → Laravel health check (also what Railway's healthcheck hits).
- `https://<domain>/` → the SPA login screen.
- Log in with `ADMIN_EMAIL` / `ADMIN_PASSWORD`, open a ticket, send a reply — it
  emails the contact over Gmail SMTP.

Each deploy runs `migrate --force` and re-seeds the admin (both idempotent), so
the schema and login stay in sync automatically. Build/run logs are on the
service's **Deployments** tab.

---

## How it fits together

| Piece | Where |
|---|---|
| Build recipe | [`Dockerfile`](./Dockerfile) — stage 1 builds the SPA (`VITE_API_URL=""` → same-origin), stage 2 is the Laravel image with `dist/` copied into `public/` |
| Railway config | [`railway.json`](./railway.json) — Dockerfile builder + `/up` healthcheck |
| SPA fallback | [`apps/api/routes/web.php`](./apps/api/routes/web.php) — non-API paths return `index.html` |
| Proxy trust | [`apps/api/bootstrap/app.php`](./apps/api/bootstrap/app.php) — `trustProxies(at: '*')` for HTTPS behind Railway's edge |

## Troubleshooting

- **Login says "CSRF token mismatch" / cookie not set** → `SANCTUM_STATEFUL_DOMAINS`
  must equal your domain (host only), and `SESSION_SECURE_COOKIE=true`,
  `APP_URL=https://<domain>`.
- **Healthcheck failing / boot crash** → check the deploy logs; usually a missing
  `APP_KEY` or a `DB_URL` that isn't the `${{Postgres.DATABASE_URL}}` reference.
- **Assets 404** → confirm the build's stage 1 succeeded (SPA `dist/` must exist
  to be copied into `public/`).

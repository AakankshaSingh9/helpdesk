# Getting Started — A Step-by-Step Walkthrough

This doc explains **what we set up, why each step was necessary, and how to run the project** with Docker. It's written for understanding, not just copy-paste. If you're new to the project, read top to bottom once; afterwards you'll mostly live in the [Daily workflow](#daily-workflow) section.

See [project-scope.md](project-scope.md) for *what* we're building and the full phased plan. This doc is about *how to run it*.

---

## 1. The big picture

This is a **monorepo**: one folder holding two apps that run together.

```
helpdesk/
├── apps/
│   ├── api/   → Laravel 12 (PHP) — the backend REST API + database access
│   └── web/   → Vue 3 + TypeScript — the frontend the agents use in the browser
├── docker-compose.yml   → defines the 5 containers that make up the dev environment
├── package.json         → handy shortcut commands (pnpm/npm run …)
└── GETTING-STARTED.md   → you are here
```

**Why two apps instead of one?** The backend and frontend are decoupled — the Vue app is a separate program that talks to the Laravel API over HTTP. This is the "SPA + API" architecture (as opposed to Laravel rendering HTML pages itself). It means the frontend and backend can evolve independently, and later you could point a mobile app at the same API.

**Why a monorepo?** Both apps live in one repository so they're versioned together, set up with one command, and share the same Docker environment. You don't have to clone two repos and keep them in sync.

---

## 2. Why Docker?

Running this project *without* Docker would mean installing PHP 8.3, Composer, PostgreSQL, the pgvector extension, Redis, and Node 22 directly on your machine — and keeping their versions matched to the project. That's fragile and machine-specific.

**Docker packages each of those into a container** — a small, isolated box with exactly the right software inside. The only thing you need installed on your machine is Docker itself. Everyone who works on the project gets the identical environment.

We use **5 containers**, defined in `docker-compose.yml`:

| Container | What it is | Why it's there |
|---|---|---|
| `db` | PostgreSQL **with pgvector** | Stores tickets, users, and (later) the AI knowledge-base vectors. We use the `pgvector/pgvector` image so the vector search extension is already installed. |
| `redis` | Redis | A fast in-memory store. Used later for background job queues. |
| `api` | PHP 8.3 + Laravel | Runs the backend on port **8000**. |
| `worker` | PHP 8.3 + Laravel | Runs `php artisan queue:work` — processes the background jobs the API dispatches (ticket classification, KB auto-resolve) so intake stays non-blocking. Shares the `api` image and code. |
| `web` | Node 22 + Vite | Runs the Vue dev server on port **5173**. |

`docker-compose.yml` is the single file that describes all five and how they connect. `docker compose up` reads it and starts everything.

---

## 3. What we did to get here, and why

These steps were already done during setup. You don't need to repeat them — this section is so you understand how the project came to exist.

### Step 1 — Took ownership of the project folder
```
sudo chown -R enjay:enjay /var/www/html/helpdesk
```
**Why:** the folder was owned by `root`, so normal tools (and the editor) couldn't create files in it. `chown` made your user the owner. Without this, nothing else below would have been writable.

### Step 2 — Scaffolded the Laravel API
```
docker run --rm -u 1000:1000 -v "$(pwd)/apps:/app" -w /app \
  composer:2 create-project "laravel/laravel:^12.0" api
```
**Why:** this generates a fresh Laravel 12 project in `apps/api`. We ran it *inside a throwaway Composer container* (`docker run --rm`) so we didn't need PHP or Composer installed on the machine. `-u 1000:1000` makes the generated files owned by you, not root. We pinned `^12.0` because the project standardized on Laravel 12 (the default would have installed the newer Laravel 13).

### Step 3 — Scaffolded the Vue SPA
```
docker run --rm -u 1000:1000 -v "$(pwd)/apps:/app" -w /app \
  node:22-alpine npm create vite@latest web -- --template vue-ts
```
**Why:** generates the Vue 3 + TypeScript app in `apps/web`, again using a throwaway container so Node didn't need to be on the machine. The `vue-ts` template gives us Vue with TypeScript out of the box.

### Step 4 — Wrote the Docker + config files
We added by hand:
- **`apps/api/Dockerfile`** — recipe to build the PHP container (installs the `pdo_pgsql` and `redis` PHP extensions Laravel needs to talk to Postgres/Redis).
- **`apps/web/Dockerfile`** — recipe to build the Node container.
- **`docker-compose.yml`** — wires the 5 containers together.
- **`apps/api/.env`** — pointed Laravel at the `db` container (Postgres) instead of the default SQLite, and at the `redis` container. Also set the Sanctum "stateful domains" so the SPA is allowed to log in.
- **`apps/web/.env`** — told the SPA where the API lives (`VITE_API_URL=http://localhost:8000`).
- A small **health-check page** in the SPA that calls the API, so we can visually confirm the two apps are talking.

**Why `.env` matters:** Laravel and Vite both read configuration from `.env` files. This is where environment-specific settings (database host, passwords, URLs) live — never hard-coded in the source.

### Step 5 — Built the images and initialized the backend
```
docker compose build
docker compose up -d db redis
docker compose run --rm api php artisan install:api   # adds the API routes + Sanctum auth
```
**Why:** `build` turns the Dockerfiles into runnable images. We start `db` first so it's ready before the API tries to connect. `install:api` is a Laravel command that creates `routes/api.php` and installs **Sanctum** (the package that will handle login/auth between the SPA and API).

### Step 6 — Added email/password auth (Laravel Fortify)
```
docker compose exec api composer require laravel/fortify
docker compose exec api php artisan fortify:install
```
**Why:** **Fortify** is Laravel's *headless* auth backend — it provides the email/password `POST /register`, `POST /login`, and `POST /logout` endpoints **without generating any UI** (we'll build the login screen in the Vue SPA later). We configured it for our SPA setup in `apps/api/config/fortify.php`: `guard => 'web'` and `middleware => ['web']` (cookie/session based), `views => false` (no server-rendered pages), and trimmed the feature list down to just registration + login (password reset, profile/password update, 2FA, and passkeys are off for now). The matching `FortifyServiceProvider` is registered in `apps/api/bootstrap/providers.php`.

**Sessions live in Postgres.** `SESSION_DRIVER=database` in `apps/api/.env` means every login is a row in the `sessions` table (already created by Laravel's default migration) — no extra session store needed. The existing `users` table migration already has `name`/`email`/`password`, so no new user migration was required.

**One-line glue:** we enabled `$middleware->statefulApi()` in `apps/api/bootstrap/app.php` so the SPA's session cookie is accepted by the `auth:sanctum`-guarded `api` routes (this is the Sanctum SPA pattern — Fortify logs the user in, Sanctum recognises the session on API calls).

**How the SPA logs in (the flow):** `GET /sanctum/csrf-cookie` (sets the XSRF cookie) → `POST /register` or `POST /login` with the `X-XSRF-TOKEN` header → authenticated requests to `/api/...` carry the session cookie automatically. `POST /logout` ends the session.

---

## 4. Daily workflow

This is what you'll actually use day to day.

### Start everything
```
docker compose up
```
or, using the shortcut in `package.json`:
```
npm run up
```
This starts all 5 containers. The first time, the `api` and `web` containers will install their dependencies (Composer packages / npm packages) — so the first boot is slower. The `worker` container shares the `api` code and waits for those Composer packages before it starts working the queue. Leave the terminal open; logs from all services stream here.

Once it's up:
- **API** → http://localhost:8000
- **SPA** → http://localhost:5173  ← open this in your browser
- The SPA page will show a green "✅ ok" if it successfully reached the API.

### Stop everything
Press `Ctrl+C` in the terminal, then:
```
docker compose down
```
(`down` removes the containers but keeps your database data, which lives in a Docker volume.)

### Run in the background instead
```
docker compose up -d      # -d = detached; runs in background
docker compose logs -f    # follow the logs
docker compose down       # stop
```

### Useful commands

| Goal | Command |
|---|---|
| Open a shell in the API container | `docker compose exec api sh` |
| Run an artisan command | `docker compose exec api php artisan <cmd>` |
| Open a shell in the web container | `docker compose exec web sh` |
| Open a Postgres prompt | `docker compose exec db psql -U helpdesk -d helpdesk` |
| Rebuild after changing a Dockerfile | `docker compose up --build` |
| See what's running | `docker compose ps` |

(The `package.json` has short aliases for several of these: `npm run api`, `npm run web`, `npm run db`, `npm run logs`.)

### Where do I edit code?
Right here on your machine, in `apps/api` and `apps/web`, with your normal editor. The folders are **mounted** into the containers (a "bind mount"), so any file you save is instantly visible inside the running container. Laravel and Vite both auto-reload on change — no rebuild needed for code edits. You only rebuild (`--build`) when you change a `Dockerfile` or add a system-level dependency.

---

## 5. Mental model: what to do when

| Situation | What to do |
|---|---|
| Starting work for the day | `docker compose up` (or `-d`) |
| Changed a `.vue`, `.ts`, or `.php` file | Nothing — it auto-reloads |
| Added a Composer package | `docker compose exec api composer require <pkg>` |
| Added an npm package | `docker compose exec web npm install <pkg>` |
| Created a database migration | `docker compose exec api php artisan migrate` |
| Changed a `Dockerfile` or `.env` | `docker compose up --build` (env changes need a restart) |
| Something's in a weird state | `docker compose down` then `docker compose up` |
| Want to wipe the database and start fresh | `docker compose down -v` (the `-v` also deletes the data volume) |

---

## 5b. Email → ticket intake

Inbound support email becomes a ticket through a **provider-agnostic webhook**:
a mail provider (or, for local testing, `curl`) POSTs a raw RFC822 message to
`POST /api/mail/inbound` and the API opens or threads a ticket. The parsing +
threading logic lives in `app/Services/Mail/InboundEmailService.php`, so a future
IMAP poller can reuse it without change.

The endpoint is unauthenticated (there's no user session behind an SMTP relay) but
requires a shared secret in the `X-Inbound-Secret` header, matched against
`MAIL_INBOUND_SECRET` in `apps/api/.env`. A **blank** secret disables the endpoint.

**Try it locally** (with `MAIL_INBOUND_SECRET=local-inbound-secret`):

```bash
curl -X POST http://localhost:8000/api/mail/inbound \
  -H "X-Inbound-Secret: local-inbound-secret" \
  -H "Content-Type: message/rfc822" \
  --data-binary @apps/api/tests/Fixtures/emails/new.eml
# → 202 {"outcome":"created","reference":"TKT-XXXXXX"}
```

Log in to the SPA and open **Tickets** (or the "View tickets" button on the home
page) to see it. What the pipeline handles:

- **Threading** — replies attach to their ticket via the `In-Reply-To`/`References`
  headers or a `[TKT-XXXXXX]` token in the subject.
- **Deduplication** — a repeated `Message-ID` is a no-op (idempotent redelivery).
- **Loop/auto-responder guard** — mail with `Auto-Submitted`/`Precedence: bulk`
  headers, or sent from our own `HELPDESK_SUPPORT_ADDRESS`, is dropped.

Feature tests with `.eml` fixtures live in `apps/api/tests/Feature/InboundEmailTest.php`
(`docker compose exec api php artisan test`).

---

## 5b-i. Dashboard

The home page's **Dashboard** button (or `/dashboard`) opens a ticket-overview
screen: headline counts for **active** (open), **resolved**, **priority** (open
refund requests), and **total** tickets, plus a **tickets-by-category** bar
chart. Everything recomputes against four filters — **date range**, **category**,
**status**, and **assigned agent** — so the same view answers focused questions
like "open refunds assigned to nobody in the last 7 days".

It's a plain read of the ticket table (no AI): the SPA calls
`GET /api/dashboard`, which accepts `range` (`7`/`30`/`90`/`all`), `category`,
`status`, and `assigned_to` (an agent id or `unassigned`) and returns the counts
and per-category totals. Available to both roles, like the other ticket reads.

---

## 5c. AI features (OpenAI `gpt-5-nano`)

Four AI features are built on OpenAI, called over REST **from PHP** (no JS SDK;
the key never leaves the server). All sit behind the `AI_ENABLED` switch and
**degrade to the manual helpdesk** when AI is off or a call fails:

| Feature | Where | Behaviour when AI is off |
|---|---|---|
| **Reply polish** | "Polish" button in the ticket reply composer | Returns the draft unchanged |
| **Ticket summary** | "Summarize" button above the ticket conversation | No summary; the full thread is still shown |
| **Auto-classification** | Queued job on new-ticket arrival | Ticket stays uncategorised |
| **KB auto-resolve** | Queued job on new-ticket arrival | Ticket stays open for an agent |

**Enable it locally:**

1. Set in `apps/api/.env`: `AI_ENABLED=true` and `OPENAI_API_KEY=sk-…`
   (`OPENAI_MODEL` defaults to `gpt-5-nano`). Restart the `api` container so it
   re-reads `.env`.
2. Classification and auto-resolve run on the **queue**, so a worker must be
   running to process them:
   ```
   docker compose exec api php artisan queue:work
   ```
   (Intake stays fast because these run off the request — the webhook returns
   `202` immediately and the jobs are picked up by the worker.)

**Knowledge base:** auto-resolve answers strictly from a single file,
`apps/api/storage/app/knowledge-base.md` (path configurable via `AI_KB_PATH`).
Edit that file to change what can be auto-answered; if the KB can't ground an
answer, the ticket is left open (never guessed). A resolved ticket gets the AI
reply recorded as an outbound message — actual email delivery is deferred Phase 2
outbound work.

---

## 6. Troubleshooting

- **SPA shows "❌ Could not reach API"** → the `api` container may still be installing dependencies on first boot. Wait, refresh. Check `docker compose logs api`.
- **`port is already allocated`** → something else on your machine is using 8000 / 5173 / 5432. Stop it, or change the port mapping in `docker-compose.yml`.
- **Permission errors on files** → the containers run as your user (UID 1000) specifically to avoid this. If you still hit it, check the file isn't owned by root from an earlier step.
- **Database connection refused** → the `api` waits for `db` to be healthy, but if Postgres is still starting, give it a few seconds. `docker compose logs db` shows its status.

---

## 7. What's next

**Phase 0** (foundation) is done — the monorepo runs and the two apps talk. The
**ticket domain** (Phase 1: `contacts`/`tickets`/`messages`) and **inbound email
intake** (Phase 2, webhook flavour — see [§5b](#5b-email--ticket-intake)) have now
landed: an email opens or threads onto a ticket, viewable in the SPA. Still ahead:
outbound replies and IMAP polling (rest of Phase 2), then the AI features
(classification, RAG, drafting) that layer on top from Phase 3 onward.

> **AI env placeholders are already in `apps/api/.env`** (and `.env.example`) — `AI_ENABLED`, `AI_PII_REDACTION`, `ANTHROPIC_*`, `VOYAGE_*` — set ahead of Phases 3–5. They do nothing yet: `AI_ENABLED=false` and the API keys are blank. Fill in the real keys locally when the AI phase starts; never commit them. See the env-variable reference in [CLAUDE.md](CLAUDE.md).

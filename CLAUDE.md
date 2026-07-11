# CLAUDE.md

Guidance for working in this repository. See `project-scope.md` for *what* we're
building and `GETTING-STARTED.md` for *how* to run it.

## What this is

**AI Powered Ticket Management System** — a monorepo with two apps that run
together via Docker Compose:

- `apps/api/` — Laravel 12 (PHP 8.3) REST API + database access.
- `apps/web/` — Vue 3 + TypeScript SPA (Vite), the agent-facing UI.

Backing services: PostgreSQL + pgvector (`db`), Redis (`redis`).

## Running it

```
docker compose up -d      # start all 5 containers (api, worker, web, db, redis)
docker compose ps         # see what's running
docker compose down       # stop
```

- API → http://localhost:8000
- SPA → http://localhost:5173
- Postgres → localhost:5432, Redis → localhost:6380

Common commands:

| Goal | Command |
|---|---|
| Shell in API container | `docker compose exec api sh` |
| Run artisan | `docker compose exec api php artisan <cmd>` |
| Run migrations | `docker compose exec api php artisan migrate` |
| Postgres prompt | `docker compose exec db psql -U helpdesk -d helpdesk` |
| Rebuild after Dockerfile/.env change | `docker compose up --build` |

Code in `apps/api` and `apps/web` is bind-mounted; Laravel and Vite auto-reload
on save. Only rebuild (`--build`) after Dockerfile or `.env` changes.

## Environment variables

Config lives in `apps/api/.env` (Laravel) and `apps/web/.env` (SPA). **Never
commit real secrets.** `.env.example` documents every key and must keep all
`*_API_KEY` / password fields **blank**. When you add a new credential or config
key, add it to `.env`, `.env.example`, and the table below in the same change.

Values shown below are **names + purpose only**, not the secrets themselves.

### `apps/api/.env`

| Variable | Purpose |
|---|---|
| `APP_KEY` | Laravel app encryption key (generated, not a shared secret). |
| `DB_*` | Postgres connection (`DB_PASSWORD` is the local dev password `secret`). |
| `REDIS_*` | Redis connection for queues/cache. |
| `SANCTUM_STATEFUL_DOMAINS` | SPA origins allowed to authenticate via cookies. |
| `MAIL_*` | Outbound mail (currently `log` driver — no real SMTP yet). |
| `HELPDESK_SUPPORT_ADDRESS` | The support mailbox. Loop guard for inbound email; From address for future replies. |
| `MAIL_INBOUND_SECRET` | Shared secret for the `POST /api/mail/inbound` webhook (**secret** — blank in `.env.example`; blank disables the endpoint). |
| `AWS_*` | Object storage (unused / blank until file storage is needed). |

### AI / RAG layer — Phases 3–5 (placeholders, not yet in use)

Added ahead of the AI phase. Keep `AI_ENABLED=false` until the pipeline is
wired up. Keys are blank; fill them in locally when the phase starts. Model IDs
mirror `project-scope.md` — confirm/bump to current IDs (e.g. `claude-sonnet-5`)
when implementing.

| Variable | Purpose |
|---|---|
| `AI_ENABLED` | Master switch for the whole AI pipeline. Default `false`. |
| `AI_PII_REDACTION` | Redact PII before any LLM call. Never disable in prod. |
| `ANTHROPIC_API_KEY` | Claude API key (**secret** — blank in `.env.example`). |
| `ANTHROPIC_CLASSIFY_MODEL` | Model for ticket classification (`claude-haiku-4-5`). |
| `ANTHROPIC_DRAFT_MODEL` | Model for reply drafting (`claude-sonnet-4-6`). |
| `ANTHROPIC_FALLBACK_MODEL` | Escalation model when quality is insufficient (`claude-opus-4-8`). |
| `VOYAGE_API_KEY` | Voyage AI key for embeddings (**secret** — blank in `.env.example`). |
| `VOYAGE_EMBED_MODEL` | Embedding model for the pgvector KB (`voyage-3`). |
| `OPENAI_API_KEY` | Key for the OpenAI-compatible LLM used by reply-polish / classification / KB auto-resolve (**secret** — blank in `.env.example`). For the default Groq provider this is a `gsk_…` key from https://console.groq.com/keys. |
| `OPENAI_BASE_URL` | Base URL of the OpenAI-compatible endpoint. Default `https://api.groq.com/openai/v1` (Groq). Point at any compatible provider to switch. |
| `OPENAI_MODEL` | Model for those features (`llama-3.3-70b-versatile` on Groq). |
| `AI_KB_PATH` | Path to the plain-text/Markdown knowledge-base file used to auto-resolve tickets. |

> **Note — non-Claude deviation.** Reply-polish, ticket classification, and KB
> auto-resolve were built against an **OpenAI-compatible Chat Completions
> endpoint** (called over REST from PHP — key stays server-side, no JS SDK) at
> the product owner's explicit request, deviating from the Claude-only
> convention above. The provider is configurable via `OPENAI_BASE_URL`; the
> current default is **Groq's free tier** running `llama-3.3-70b-versatile`
> (the `OPENAI_*` env/config names are kept for backwards compatibility). The
> Claude/Voyage keys remain for the planned RAG pipeline. All three features
> stay behind `AI_ENABLED` and degrade to the manual helpdesk when AI is off or
> a call fails.

### `apps/web/.env`

| Variable | Purpose |
|---|---|
| `VITE_API_URL` | Base URL of the Laravel API the SPA calls. |

## Conventions

- All AI/RAG calls are made from PHP over REST (Claude + Voyage). No Python in
  the web app; if heavy local ML is ever needed, add a Python sidecar.
- Every AI step must have a non-AI fallback (degrade to a normal helpdesk).
- Keep `GETTING-STARTED.md` and `project-scope.md` in sync with any change.

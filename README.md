<div align="center">

# 🎫 AI-Powered Helpdesk

**Turn inbound support email into tickets, auto-resolve the easy ones from a knowledge base, and let agents reply — with an AI assist — in a clean Vue workspace.**

[![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?logo=php&logoColor=white)](https://php.net)
[![Vue](https://img.shields.io/badge/Vue-3-4FC08D?logo=vuedotjs&logoColor=white)](https://vuejs.org)
[![TypeScript](https://img.shields.io/badge/TypeScript-5-3178C6?logo=typescript&logoColor=white)](https://www.typescriptlang.org)
[![Postgres](https://img.shields.io/badge/PostgreSQL-16_+_pgvector-4169E1?logo=postgresql&logoColor=white)](https://www.postgresql.org)
[![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?logo=docker&logoColor=white)](https://docs.docker.com/compose/)

</div>

---

## ✨ Features

- 📥 **Email → ticket intake** — a provider-agnostic webhook turns inbound mail into tickets, threading follow-ups onto the right conversation and de-duplicating redelivery.
- 🤖 **AI pipeline** — new tickets are auto-classified and, when a knowledge-base answer is confident, auto-resolved. Every AI step degrades gracefully to a plain helpdesk if AI is off or a call fails.
- ✍️ **Agent replies over SMTP** — write a reply (optionally **Polish** it with AI), hit send, and it's emailed to the customer, threaded into their inbox. A send only records once delivery succeeds.
- 🧠 **AI ticket summaries** — one click condenses a long thread so an agent can pick up context fast.
- 📊 **Dashboard** — live counts (active / resolved / priority / total) and a tickets-by-category chart, filterable by date, category, status, and assignee.
- 👥 **Roles & admin** — Sanctum cookie-based SPA auth (Laravel Fortify), admin-only user management, mass-assignment-guarded role boundaries.
- 🌙 **Light / dark mode** — a manual theme toggle that also respects the OS preference.

## 🏗️ Architecture

A monorepo with two apps that run together under Docker Compose:

```
┌─────────────┐     HTTP      ┌──────────────┐
│  apps/web   │ ────────────▶ │   apps/api   │
│  Vue 3 SPA  │  Sanctum      │  Laravel 12  │
│  (Vite)     │  cookie auth  │  REST API    │
└─────────────┘               └──────┬───────┘
                                      │
                        ┌─────────────┼─────────────┐
                        ▼             ▼             ▼
                  ┌──────────┐  ┌──────────┐  ┌──────────┐
                  │ Postgres │  │  Redis   │  │  worker  │
                  │+ pgvector│  │ (queues) │  │ (queue)  │
                  └──────────┘  └──────────┘  └──────────┘
```

| Service | Stack | Purpose |
|---|---|---|
| `web` | Vue 3 + TypeScript + Vite | Agent-facing SPA |
| `api` | Laravel 12 (PHP 8.3) | REST API + auth + AI pipeline |
| `worker` | Laravel queue worker | Background jobs (classify, auto-resolve) |
| `db` | PostgreSQL 16 + pgvector | Tickets, messages, KB embeddings |
| `redis` | Redis 7 | Queue / cache backend |

> **AI provider.** Reply-polish, classification, and KB auto-resolve call an OpenAI-compatible Chat Completions endpoint over REST — default is **Groq's free tier** (`llama-3.3-70b-versatile`), configurable via `OPENAI_BASE_URL`. Claude + Voyage keys are reserved for the planned RAG phase.

## 🚀 Quick start

**Prerequisites:** Docker + Docker Compose.

```bash
git clone https://github.com/AakankshaSingh9/helpdesk.git
cd helpdesk

# API + SPA each read their own .env — copy the examples and fill in as needed
cp apps/api/.env.example apps/api/.env
cp apps/web/.env.example apps/web/.env

docker compose up -d          # start all 5 containers
docker compose exec api php artisan migrate --seed
```

Then open:

| App | URL |
|---|---|
| 🖥️ SPA | http://localhost:5173 |
| ⚙️ API | http://localhost:8000 |
| 🗄️ Postgres | `localhost:5432` |
| 🔴 Redis | `localhost:6380` |

Code in `apps/api` and `apps/web` is bind-mounted — Laravel and Vite auto-reload on save. Only rebuild (`docker compose up --build`) after a Dockerfile or `.env` change.

## 🧰 Common commands

| Goal | Command |
|---|---|
| See what's running | `docker compose ps` |
| Shell into the API | `docker compose exec api sh` |
| Run artisan | `docker compose exec api php artisan <cmd>` |
| Run migrations | `docker compose exec api php artisan migrate` |
| Postgres prompt | `docker compose exec db psql -U helpdesk -d helpdesk` |
| Run the API tests | `docker compose exec api php artisan test` |
| Type-check the SPA | `docker compose exec web npx vue-tsc -b` |
| Stop everything | `docker compose down` |

## 📁 Project structure

```
helpdesk/
├── apps/
│   ├── api/            # Laravel 12 — REST API, queues, AI pipeline, tests
│   └── web/            # Vue 3 + TypeScript + Vite SPA (agent workspace)
├── Dockerfile          # Production image (SPA + API, one origin) — used by Railway
├── railway.json        # Railway deploy config
├── docker-compose.yml  # Local dev: api, worker, web, db, redis
├── CLAUDE.md           # Repo guide + full env-var reference
├── GETTING-STARTED.md  # How the app was built, step by step
├── RAILWAY-DEPLOY.md   # Production deploy walkthrough
└── project-scope.md    # What we're building + why
```

## ⚙️ Configuration

Config lives in `apps/api/.env` (Laravel) and `apps/web/.env` (SPA). **Never commit real secrets** — `.env` is git-ignored and `*_API_KEY` / password fields stay blank in the `.env.example` files. Every key is documented in **[CLAUDE.md](./CLAUDE.md#environment-variables)**.

Notable switches:

- `AI_ENABLED` — master switch for the whole AI pipeline (defaults to `false`; the app runs as a plain helpdesk when off).
- `MAIL_MAILER` — `smtp` to deliver agent replies (default `.env` is Gmail SMTP), or `log` to write mail to the log instead.
- `MAIL_INBOUND_SECRET` — shared secret for the inbound-email webhook (blank disables it).

## 🧪 Testing

```bash
docker compose exec api php artisan test        # Laravel feature/unit tests
docker compose exec web npm run test            # Vitest (SPA)
docker compose exec web npx vue-tsc -b          # SPA type-check
```

## ☁️ Deployment

The repo ships a production `Dockerfile` that builds the SPA and serves it from the Laravel container as a **single same-origin service** (keeps Sanctum's cookie auth first-party — no CORS headaches). See **[RAILWAY-DEPLOY.md](./RAILWAY-DEPLOY.md)** for the full Railway walkthrough (add Postgres, set env vars, generate a domain).

## 📚 Docs

| Doc | What's in it |
|---|---|
| [CLAUDE.md](./CLAUDE.md) | Repo conventions + complete environment-variable reference |
| [GETTING-STARTED.md](./GETTING-STARTED.md) | Build log — how each piece was added and why |
| [project-scope.md](./project-scope.md) | Product scope, success metrics, roadmap |
| [RAILWAY-DEPLOY.md](./RAILWAY-DEPLOY.md) | Production deployment on Railway |

---

<div align="center">
<sub>Built with Laravel, Vue, and PostgreSQL.</sub>
</div>

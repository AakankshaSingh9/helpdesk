# AI Powered Ticket Management System

## Problem
- Manage tickets
- Reduce agents' time on repetitive tickets
- Faster and humanized reply instead of canned ones

## Solution
- An application that handles inbound support emails, raises tickets, replies automatically from a knowledge base, and assigns to an agent only when human intervention is needed.

## Primary success metric
- Faster first response to customers.
- Guardrail: track AI-draft edit-rate / auto-reply correctness so "fast" does not become "fast but wrong".

## Scope decisions (V1)
- **Build:** from scratch (not on top of an existing helpdesk).
- **Channels:** email only.
- **Email:** low volume (<100/day), Google Workspace inbox.
- **AI autonomy:** hybrid by category — auto-send for safe intents, draft-for-agent-approval for sensitive ones, straight-to-human when unclear.
- **Knowledge base:** built from existing docs/FAQ + past resolved tickets (RAG). Past tickets are PII-scrubbed before indexing.
- **Privacy:** undecided. Default design posture: cloud LLM with a PII-redaction step before sending content to the model (built in, not retrofitted). To confirm: whether customers fall under a regulated region.
- **Tech stack:** **Monorepo** with a Laravel 12 (PHP) REST API and a decoupled Vue 3 + TypeScript SPA, on PostgreSQL. The two apps run together in dev. AI/RAG layer talks to Claude & Voyage over their REST APIs from PHP (no Python in the web app). If heavier local ML is ever needed, add a small Python AI sidecar rather than reworking the app.

## Tech stack
| Layer | Choice |
| --- | --- |
| Repo layout | **Monorepo, pnpm workspaces** — `apps/api` (Laravel) + `apps/web` (Vue SPA); root `pnpm dev` runs both |
| Backend framework | Laravel 12 (PHP 8.3+) — REST API, queues, scheduler |
| API | Laravel routes/controllers + API resources; **Sanctum** cookie-based SPA auth (CORS + stateful domains) |
| Frontend | **Vue 3 + TypeScript** (Composition API, `<script setup lang="ts">`) + Vite; typecheck with `vue-tsc`, lint with ESLint |
| Frontend wiring | Decoupled **Vue SPA ↔ Laravel API** over HTTP (no Inertia — two apps run separately) |
| Admin panel | **Filament**, served from `apps/api` (server-rendered; separate from the SPA) — agent/user CRUD, KB management, ticket oversight, reporting |
| Dev docs | **Context7 MCP** for up-to-date library docs (Laravel 12, Vue, Sanctum, pgvector, Voyage) during the build |
| Auth & roles | **Laravel Fortify** (headless email/password, database sessions, no UI) + **spatie/laravel-permission** for admin/agent roles |
| Database | PostgreSQL + **pgvector** (tickets, KB chunks, embeddings in one DB) |
| Vector access | `pgvector/pgvector-php` or raw SQL via Eloquent (no ORM-native vector type) |
| Background jobs | **Laravel Queues + Horizon** on Redis (email polling, classification, drafting) |
| Scheduler | Laravel Scheduler (cron) drives the ~1 min IMAP poll |
| Email in | IMAP polling via **`webklex/php-imap`** (or Mailgun/Postmark inbound webhook) |
| Email out | Laravel Mail over Google Workspace SMTP; thread on In-Reply-To/References + subject ticket token |
| LLM | Claude API (REST) — `claude-haiku-4-5` for classification, `claude-sonnet-4-6` for drafting (→ `claude-opus-4-8` if needed) |
| Embeddings | Voyage AI (REST), stored in pgvector |
| PII redaction | Pre-LLM pipeline stage (regex first, model only if needed) |
| Real-time (optional) | **Laravel Reverb** (WebSockets) for live ticket-queue / draft updates |
| Hosting | Single VPS: Nginx + PHP-FPM, Postgres, Redis, Supervisor for queue workers + scheduler |
- RAG is hand-written (embed query → pgvector search → top chunks into Claude prompt); no framework needed at this scale.

## Repository structure (monorepo)
```
helpdesk/
├── apps/
│   ├── api/            # Laravel 12 — REST API, queues, scheduler, Filament admin, AI pipeline
│   └── web/            # Vue 3 + TypeScript + Vite SPA (agent workspace)
├── package.json        # pnpm workspace root + run scripts
└── pnpm-workspace.yaml
```
- Run both in dev with a root `pnpm dev` (concurrently): `api` (`php artisan serve` + `queue:work` + `schedule:work`) and `web` (Vite).
- `web` ↔ `api` over HTTP; **Sanctum** cookie-based SPA auth (CORS + stateful domains configured).
- `shared-types` package deferred for V1 — the SPA defines its own types; revisit if API/SPA contracts drift.

## Features
- Ticket management (status-wise)
- Automatic reply based on knowledge base
- Assign tickets to active agents when human intervention is needed
- Improve / humanize the reply message

## Users & roles
- **Admin:** the system is deployed with a single admin. The admin can create additional agents and manage the system.
- **Agent:** handles tickets escalated by the system; reviews, edits, and sends AI-drafted replies.

## Ticket model
- **Statuses:** open, resolved, closed.
- **Category (single per ticket):** general question, technical question, refund request.

## Category → action policy
| Category | Default action |
| --- | --- |
| General question | Auto-reply from KB when a grounded answer is found; otherwise escalate |
| Technical question | Draft reply for agent approval |
| Refund request | Escalate straight to a human agent |
- Fallback: if the category is unclear or no relevant KB match is found, escalate to a human — never guess.

## Core pipeline
1. Ingest inbound email (thread to existing ticket or open a new one).
2. Classify intent into a category.
3. Apply the category → action policy.
4. Retrieve grounded answer from the knowledge base (RAG).
5. Auto-send, draft for agent approval, or assign to a human accordingly.

## Implementation plan (phased, basics-first)
Each phase ends in something demonstrable. Build the manual helpdesk first, layer AI on top — so if the AI is switched off the app degrades to a normal ticketing tool. Heavier pieces (pgvector, Filament, spatie, Horizon) enter only when a phase needs them.

### Phase 0 — Minimal foundation
- Scaffold monorepo (pnpm workspaces, `apps/api` + `apps/web`), root `pnpm dev` to run both.
- Init Laravel 12 in `apps/api`; connect PostgreSQL (plain, no pgvector yet).
- Vite + Vue 3 + **TypeScript** + ESLint + `vue-tsc` in `apps/web`; one smoke-test page.
- Sanctum + CORS so the SPA logs in against the API; **Fortify** headless email/password auth endpoints (register/login/logout), sessions stored in Postgres.
- Seed one admin via a simple `role` column on `users` (defer spatie).
- Basic CI: Pint, PHPStan/Larastan, `vue-tsc`, tests.
- **Exit:** admin logs in through the SPA against the API.

### Phase 1 — Minimal ticket domain (no AI, no email) — *partially landed*
- ✅ Migrations/models/factories: `tickets`, `messages`, `contacts` (+ status/category enums).
- ✅ Read-only ticket list (server-side sortable columns via TanStack Table) + conversation view in the SPA; API `GET /api/tickets` (accepts `sort`/`direction`), `/api/tickets/{id}`.
- ⬜ Still to do: reply-from-UI, manual assign-to-agent + authorization (admin all / agent assigned), lifecycle tests.
- Statuses (open/resolved/closed) + categories (general/technical/refund) as enums.
- Ticket CRUD + reply thread: API endpoints (`apps/api`) + SPA screens (`apps/web`).
- Manual "assign to agent" + authorization (admin sees all, agent sees assigned).
- Seeders + feature tests for the lifecycle.
- **Exit:** an agent works a ticket end-to-end by hand.

### Phase 2 — Email in/out — *inbound landed (webhook)*
- ✅ Inbound via a **provider-agnostic webhook** (`POST /api/mail/inbound`, shared-secret
  guarded) parsing raw RFC822 with `zbateson/mail-mime-parser`. Logic sits behind
  `InboundEmailService` so the IMAP poller below can reuse it.
- ✅ Parse + thread (In-Reply-To/References + subject token); dedupe by Message-ID.
- ✅ Loop/auto-responder prevention; empty-body handling. Tests with `.eml` fixtures.
- ⬜ Still to do: IMAP polling via `webklex/php-imap` on the Scheduler (share the service);
  outbound via Laravel Mail over Workspace SMTP, correctly threaded; attachments/spam.
- **Exit:** email creates a ticket; agent reply lands in-thread. *(Usable manual helpdesk — shippable.)*

### Phase 3 — AI classification & routing
- Claude API client wrapper (retries, timeouts, cost/latency logging).
- PII redaction stage (regex first) — runs before any LLM call.
- Classify with `claude-haiku-4-5` → category + confidence; store with model/prompt version.
- Category→action policy engine; low-confidence/unclear → escalate.
- Fallback: LLM error → create ticket + assign human.
- **Exit:** new tickets are categorized and routed; drafting stubbed.

### Phase 4 — Knowledge base & RAG
- Add **pgvector** (extension + `kb_documents`, `kb_chunks` with vector column/index).
- KB ingest + chunking; past-ticket ingest with PII scrub.
- Voyage embeddings on ingest; query → pgvector top-K; grounding threshold.
- Tests for retrieval relevance.
- **Exit:** system returns relevant KB chunks or a confident "no match".

### Phase 5 — AI drafting & autonomy
- Draft with `claude-sonnet-4-6` + retrieved chunks + tone guidelines (cite sources).
- Three lanes: auto-send (gated), draft-for-approval, escalate.
- Agent assignment rule (round-robin among "active" agents).
- "Humanize/regenerate" action; capture edit-rate + correctness guardrail.
- Never auto-send refunds/low-confidence (enforced + tested).
- **Exit:** inbound email → classified → grounded draft → auto-sent or queued.

### Phase 6 — Agent workspace (Vue + TS)
- Queue (filters by status/category/assignment), conversation view.
- AI draft surface: view + cited sources; approve / edit / send; regenerate.
- Status/assignment controls. (Laravel Reverb live updates optional.)
- **Exit:** agent does the whole job in the UI.

### Phase 7 — Admin, reporting & hardening
- Add **Filament** (in `apps/api`) for agent + KB management; add **spatie/laravel-permission** if the simple role column has outgrown itself.
- Dashboards: first-response time (primary metric), auto/draft/escalate mix, edit-rate, LLM cost.
  - ✅ Early slice landed: a ticket-volume dashboard in the SPA (`/dashboard`) — active/resolved/priority(open-refund)/total counts + a tickets-by-category bar chart, all scoped by category/status/assigned-agent/date-range filters. API `GET /api/dashboard` (accepts those filters). The AI-quality metrics above are still to come.
- Audit log of AI decisions; structured logging + alerting.
- **Exit:** admin can answer "fast, and correct?".

### Phase 8 — Deploy
- VPS: Nginx + PHP-FPM, Postgres, Redis, Supervisor (queues + scheduler); promote to **Horizon** here.
- Secrets, backups + restore drill, rate limiting, security review, runbook, staging→prod.
- **Exit:** runs in production; degrades to a normal helpdesk if AI/email fail.

### Cross-cutting (every phase)
- Tests alongside features; keep CI green.
- Prompt + model versions stored with results (auditable, reproducible).
- Every AI step has a non-AI fallback.
- PII never reaches an LLM un-redacted.
- Use **Context7** to pull current docs for whatever library a phase touches.

## Open questions / to decide
- AI integration shape: all-PHP (HTTP calls to Claude/Voyage) vs. a small Python AI sidecar — default all-PHP.
- Privacy/compliance posture (regulated region?).
- Email plumbing: IMAP polling vs Gmail API; threading rules; loop prevention; spam & attachment handling.
- Knowledge base ownership and freshness (who curates; do resolved tickets feed back in, and with what approval?).
- Agent assignment rule (e.g. round-robin among available agents) and definition of an "active" agent.
- Agent-facing app: ticket queue, conversation view, approve/edit/send surface for AI drafts, KB management, admin/reporting view.
- Failure modes: on LLM/KB failure or low-confidence classification, default to creating a ticket and assigning a human (degrade to a normal helpdesk).
- Tone/brand guidelines and language handling for AI replies.

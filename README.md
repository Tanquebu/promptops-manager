![PHPUnit](https://github.com/Tanquebu/promptops-manager/actions/workflows/tests.yml/badge.svg)
![License](https://img.shields.io/badge/license-MIT-blue.svg)
![PHP](https://img.shields.io/badge/PHP-8.3-8892BF.svg)
![Laravel](https://img.shields.io/badge/Laravel-11-FF2D20.svg)
![Docker](https://img.shields.io/badge/requires-Docker-2496ED.svg)

# PromptOps Manager

**Version your LLM prompts like code. Test them like features. Ship them with confidence.**

PromptOps Manager is a self-hosted, open-source registry for LLM prompts. It solves the problem of prompts living scattered across `.env` files, config files, and hardcoded strings — with no versioning, no tests, and no promotion workflow. Teams get a structured path from writing a prompt to promoting it through development, staging, and production, with automated regression tests to catch regressions before they reach users. Built with Laravel 11 and React 18. No Python. No cloud lock-in.

---

## Key features

- **Prompt versioning** — every change creates a numbered version; nothing is overwritten.
- **Environment promotion** — promote specific versions to `development`, `staging`, or `production` independently.
- **Variable interpolation** — define named variables (`{{text}}`, `{{max_words}}`) and compile them at resolve time.
- **Async test runner** — queue-backed test runs with four assertion types: `contains`, `not_contains`, `regex`, `llm_judge`.
- **LLM-judge evaluation** — a second LLM call grades the response and explains its reasoning.
- **Public resolve endpoint** — `GET /api/prompts/{slug}/resolve?env=production` requires no auth; consume it from any app or script.
- **Zero vendor lock-in** — self-hosted, MIT-licensed, with an API that works identically from a browser, curl, or a future CLI.

---

## Why not Langfuse / PromptLayer / LangSmith?

|  | PromptOps Manager | Langfuse | PromptLayer | LangSmith |
|---|:---:|:---:|:---:|:---:|
| Self-hosted (full control) | ✅ | partial | ❌ | ❌ |
| PHP/Laravel native | ✅ | ❌ | ❌ | ❌ |
| Env promotion workflow (dev → staging → prod) | ✅ | partial | partial | ❌ |
| Built-in test runner with assertions | ✅ | ❌ | ❌ | partial |
| LLM-judge evaluation | ✅ | ❌ | ❌ | partial |
| No Python runtime required | ✅ | ❌ | ❌ | ❌ |
| Pricing | Free / self-hosted | Freemium / cloud | Paid / cloud | Paid / cloud |

Langfuse and LangSmith excel at tracing and observability. PromptLayer focuses on logging and A/B testing. PromptOps Manager is the only PHP-native, self-hosted tool with a complete workflow: **version → test → promote across environments** — all in one place, with zero vendor dependency.

---

## Prerequisites

| Requirement | Minimum version |
|---|---|
| Docker | 24.0 |
| Docker Compose | v2 (bundled with Docker Desktop ≥ 4.x) |
| jq | any recent version (for the curl examples below) |

No local PHP or Node installation is required — all runtimes run inside Docker.

---

## Quick start

```bash
# 1. Clone the repo
git clone https://github.com/Tanquebu/promptops-manager && cd promptops-manager

# 2. Configure environment
cp backend/.env.example backend/.env

# 3. Start all services
docker compose up -d

# 4. Bootstrap backend
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed

# 5. Open the app
# Frontend: http://localhost:5173
# API:      http://localhost:8000
```

**Default credentials:** `admin@promptops.test` / `password`

**Verify it's working:**

```bash
curl -s -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@promptops.test","password":"password"}' \
  | jq '.data.token'
# → a non-null token string confirms the API and database are up
```

> Screenshots coming soon — contributions welcome.

---

## Architecture

```
┌──────────────────────────────────────────────────────┐
│                  PromptOps Manager                   │
│                                                      │
│   React SPA (Vite)  ──►  Laravel 11 API             │
│       :5173                  :8000                   │
│                                 │                    │
│                       ┌─────────┴──────────┐         │
│                       │                    │         │
│                  PostgreSQL             Redis         │
│                  (data store)       (queue driver)   │
│                                          │           │
│                                 Queue Worker         │
│                                 (RunTestJob)         │
│                                      │               │
│                              OpenAI / Anthropic      │
│                                 LLM APIs             │
└──────────────────────────────────────────────────────┘
```

A user creates a **Prompt** with a slug and a list of variable names. Each edit produces a new **Version** (starting in `draft` status). Once a version is published, it can be **promoted** to an environment — this writes a record to `prompt_environments` that maps the prompt + environment pair to the chosen version. Any app resolves the active content by calling `GET /api/prompts/{slug}/resolve?env=production`, which compiles the version's template (replacing `{{variable}}` placeholders with supplied values) and returns the result — no auth required. **Test cases** define input variables and an expected output; a test run dispatches a Redis queue job that calls the LLM, evaluates the response, and stores `passed`/`failed`/`error` with full reasoning.

---

## API reference

All responses use the envelope format `{"data": {...}, "meta": {...}}` for success and `{"error": {"message": "...", "code": "..."}}` for errors.

**Auth header:** `Authorization: Bearer <token>`

### Auth

| Method | Path | Auth | Description |
|---|---|:---:|---|
| POST | `/api/auth/login` | — | Exchange credentials for a Bearer token |
| POST | `/api/auth/logout` | required | Invalidate the current token |
| GET | `/api/auth/me` | required | Return the authenticated user |

### Prompts

| Method | Path | Auth | Description |
|---|---|:---:|---|
| GET | `/api/prompts` | required | Paginated list of prompts |
| POST | `/api/prompts` | required | Create a prompt |
| GET | `/api/prompts/{slug}` | required | Prompt detail |
| DELETE | `/api/prompts/{slug}` | required | Delete prompt and all related data |

### Versions

| Method | Path | Auth | Description |
|---|---|:---:|---|
| GET | `/api/prompts/{slug}/versions` | required | All versions, newest first |
| POST | `/api/prompts/{slug}/versions` | required | Create a new version (always `draft`) |
| PATCH | `/api/prompts/{slug}/versions/{id}` | required | Update version status |

### Environments

| Method | Path | Auth | Description |
|---|---|:---:|---|
| GET | `/api/prompts/{slug}/environments` | required | Current version assigned to each environment |
| POST | `/api/prompts/{slug}/promote` | required | Promote `{version_id}` to `{environment}` |
| GET | `/api/prompts/{slug}/resolve` | — | Resolve compiled prompt; `?env=` and `?variables[key]=val` |

### Test cases

| Method | Path | Auth | Description |
|---|---|:---:|---|
| GET | `/api/prompts/{slug}/test-cases` | required | List test cases |
| POST | `/api/prompts/{slug}/test-cases` | required | Create a test case |
| DELETE | `/api/test-cases/{id}` | required | Delete a test case |

### Test runs

| Method | Path | Auth | Description |
|---|---|:---:|---|
| POST | `/api/test-cases/{id}/run` | required | Dispatch a run → 202 `{id, status: "pending"}` |
| GET | `/api/test-runs/{id}` | required | Poll run status until no longer `pending`/`running` |
| GET | `/api/prompts/{slug}/test-runs` | required | Run history for a prompt, newest first |

### curl examples

**Login and save token:**
```bash
TOKEN=$(curl -s -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@promptops.test","password":"password"}' \
  | jq -r '.data.token')
```

**Create a prompt:**
```bash
curl -s -X POST http://localhost:8000/api/prompts \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"slug":"summarize-text","name":"Summarize Text","variables":["text","max_words"]}'
```

**Create a version:**
```bash
curl -s -X POST http://localhost:8000/api/prompts/summarize-text/versions \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"content":"Summarize the following in {{max_words}} words:\n\n{{text}}"}'
```

**Promote to production:**
```bash
curl -s -X POST http://localhost:8000/api/prompts/summarize-text/promote \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"version_id":"<id>","environment":"production"}'
```

**Resolve a prompt (public, no auth):**
```bash
curl "http://localhost:8000/api/prompts/summarize-text/resolve?env=production&variables[text]=Hello&variables[max_words]=50"
```

---

## Configuration

### Backend (`backend/.env`)

| Variable | Required | Default | Description |
|---|:---:|---|---|
| `APP_KEY` | ✅ | — | Laravel encryption key — run `php artisan key:generate` |
| `APP_ENV` | | `local` | Application environment |
| `APP_URL` | | `http://localhost:8000` | Base URL for link generation |
| `DB_CONNECTION` | ✅ | `pgsql` | Database driver |
| `DB_HOST` | ✅ | `postgres` | PostgreSQL host (use `postgres` inside Docker) |
| `DB_PORT` | | `5432` | PostgreSQL port |
| `DB_DATABASE` | ✅ | `promptops` | Database name |
| `DB_USERNAME` | ✅ | `promptops` | Database user |
| `DB_PASSWORD` | ✅ | `secret` | Database password |
| `REDIS_HOST` | ✅ | `redis` | Redis host (use `redis` inside Docker) |
| `REDIS_PORT` | | `6379` | Redis port |
| `QUEUE_CONNECTION` | ✅ | `redis` | Must be `redis` for async test runs |
| `LLM_PROVIDER` | | `openai` | `openai` \| `anthropic` \| `fake` |
| `OPENAI_API_KEY` | | — | Required if `LLM_PROVIDER=openai` |
| `OPENAI_MODEL` | | `gpt-4o-mini` | OpenAI model to use |
| `ANTHROPIC_API_KEY` | | — | Required if `LLM_PROVIDER=anthropic` |
| `ANTHROPIC_MODEL` | | `claude-haiku-3-5-20251001` | Anthropic model to use |
| `SANCTUM_STATEFUL_DOMAINS` | | `localhost:5173` | Domains allowed to use session cookies |

### Frontend (`frontend/.env`)

| Variable | Required | Default | Description |
|---|:---:|---|---|
| `VITE_API_URL` | ✅ | `http://localhost:8000` | Base URL for all API requests |

---

## Running the test suite

```bash
docker compose exec app ./vendor/bin/phpunit
```

The suite runs against an in-memory SQLite database and a `FakeLlmClient` — **no API keys required**. It covers:

- Authentication (login, logout, me, token invalidation)
- Prompt CRUD and slug uniqueness
- Version creation, status transitions, and ordering
- Environment promotion and conflict resolution
- Prompt resolution with variable compilation
- Test case management
- All four assertion types: `contains`, `not_contains`, `regex`, `llm_judge`
- Async test run lifecycle (`pending → running → passed/failed/error`)

---

## Supported LLM providers

Set `LLM_PROVIDER` in `backend/.env`:

| Value | Provider | Default model |
|---|---|---|
| `openai` | OpenAI | `gpt-4o-mini` |
| `anthropic` | Anthropic | `claude-haiku-3-5-20251001` |
| `fake` | Hardcoded responses — no API key required | — |

The `fake` driver is used by the test suite and is safe to use in CI pipelines.

---

## Contributing

1. Fork the repository and create a branch off `master`.
2. Make your changes, add or update tests, and ensure `./vendor/bin/phpunit` passes.
3. Open a pull request with a short description of the change and the motivation.

This is an early-stage project. Issues and discussions are very welcome — especially for items on the roadmap below. See [SPEC.md](SPEC.md) for full architecture context before making structural changes.

---

## Roadmap

| Feature | Status | Notes |
|---|---|---|
| Webhook notifications on test failure | Planned | POST to a configured URL when a run reaches `failed` or `error` |
| CLI tool (`promptops push/pull/run`) | Planned | Sync prompts to local files; trigger test runs from terminal |
| GitHub Actions integration | Planned | Run test suite against a PR's prompt changes before merge |
| Team / workspace support with RBAC | Planned | Multi-tenant with owner / editor / viewer roles per workspace |
| Prompt marketplace / sharing | Planned | Export and import prompts as versioned bundles; public registry |
| Multi-turn prompt chains | Planned | Link prompts in sequence, passing outputs as inputs |

---

## License

MIT

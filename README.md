# PromptOps Manager

**Version your LLM prompts like code. Test them like features. Ship them with confidence.**

PromptOps Manager is a self-hosted, open-source registry for LLM prompts. It solves the problem of prompts living scattered across `.env` files, config files, and hardcoded strings — with no versioning, no tests, and no promotion workflow.

Built with Laravel 11 + React 18. No Python. No cloud lock-in.

---

## Why not Langfuse / PromptLayer / LangSmith?

| | PromptOps Manager | Langfuse | PromptLayer | LangSmith |
|---|:---:|:---:|:---:|:---:|
| Self-hosted (full control) | ✅ | partial | ❌ | ❌ |
| PHP/Laravel native | ✅ | ❌ | ❌ | ❌ |
| Env promotion workflow (dev → staging → prod) | ✅ | partial | partial | ❌ |
| Built-in test runner with assertions | ✅ | ❌ | ❌ | partial |
| LLM-judge evaluation | ✅ | ❌ | ❌ | partial |
| No Python runtime required | ✅ | ❌ | ❌ | ❌ |

Langfuse and LangSmith excel at tracing and observability. PromptLayer focuses on logging and A/B testing. PromptOps Manager is the only PHP-native, self-hosted tool with a complete workflow: **version → test → promote across environments** — all in one place, with zero vendor dependency.

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

**Prompt lifecycle:**
```
Prompt (slug: "summarize-text", variables: ["text", "max_words"])
  └─ Version 1  (status: archived)
  └─ Version 2  (status: published)
        ├─ promoted to staging   → staging uses v2
        └─ promoted to production → production uses v2

GET /api/prompts/summarize-text/resolve?env=production
→ returns v2 compiled content (public, no auth required)
```

---

## Quick Start

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

---

## API Examples

**Login and save token:**
```bash
TOKEN=$(curl -s -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@promptops.test","password":"password"}' \
  | jq -r '.data.token')
```

**Create a prompt:**
```bash
curl -X POST http://localhost:8000/api/prompts \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"slug":"summarize-text","name":"Summarize Text","variables":["text","max_words"]}'
```

**Resolve a prompt (public, no auth):**
```bash
curl http://localhost:8000/api/prompts/summarize-text/resolve?env=production
```

---

## Running Tests

```bash
docker compose exec app ./vendor/bin/phpunit
```

Tests use SQLite in-memory and a `FakeLlmClient` — no real API keys required. The test suite covers auth, prompt CRUD, version management, environment promotion, and all four assertion types (`contains`, `not_contains`, `regex`, `llm_judge`).

---

## Supported LLM Providers

Set `LLM_PROVIDER` in `backend/.env`:

| Value | Provider |
|---|---|
| `openai` | OpenAI (default: `gpt-4o-mini`) |
| `anthropic` | Anthropic (default: `claude-haiku-3-5-20251001`) |
| `fake` | Hardcoded responses — for tests and CI, no API key required |

---

## Roadmap

- [ ] Webhook notifications on test failure
- [ ] CLI tool (`promptops push/pull/run`)
- [ ] GitHub Actions integration
- [ ] Team/workspace support with RBAC
- [ ] Multi-turn prompt chains

---

## License

MIT

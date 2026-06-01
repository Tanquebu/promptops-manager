# PromptOps Manager

## Project overview

PromptOps Manager is an open-source tool for versioning, testing, and deploying LLM prompts in a team context. It solves the problem of prompts living scattered across config files, environment variables, and hardcoded strings. Teams get a structured workflow for versioning prompt content, promoting versions across environments (development → staging → production), and running automated regression tests against LLM responses.

## Architecture decisions

- **Laravel 11 API + React SPA**: Clean separation of concerns — the API is consumed by the SPA today and by a future CLI or GitHub Action tomorrow without changes. Laravel's ecosystem (Eloquent, Sanctum, Queues) covers all backend needs without overengineering.
- **Sanctum token-based auth (not cookie/SPA mode)**: Tokens work identically for the browser SPA, curl, and future CLI clients. Cookie-based SPA mode adds CSRF complexity and domain restrictions that are unnecessary here.
- **Async runner with Redis queue**: LLM calls can take 5–30 seconds. A queue job prevents HTTP timeouts, allows retries, and gives a clean polling model (`pending → running → passed/failed`).
- **ULID primary keys**: ULIDs are sortable by creation time (unlike UUID v4), URL-safe without encoding, and avoid integer enumeration attacks. Laravel 11 has first-class ULID support via `HasUlids`.
- **No frontend component library**: Tailwind utility classes keep the bundle small and avoid fighting a component library's opinions. The UI is simple enough that bespoke components are faster to write than to override.

## Local dev setup

```bash
# 1. Clone and enter the project
git clone <repo> promptops-manager && cd promptops-manager

# 2. Copy env files
cp backend/.env.example backend/.env
cp frontend/.env.example frontend/.env   # if it exists

# 3. Start all services
docker compose up -d

# 4. Install backend dependencies and bootstrap
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed

# 5. Frontend is served by the vite service on http://localhost:5173
# Backend API is on http://localhost:8000
```

Default credentials: `admin@promptops.test` / `password`

## Key concepts

**Prompt → Version → Environment promotion flow:**

```
Prompt (slug: "summarize-text", variables: ["text", "max_words"])
  └─ Version 1  (status: archived, content: "Summarize: {{text}}")
  └─ Version 2  (status: published, content: "Summarize in {{max_words}} words: {{text}}")
  └─ Version 3  (status: published, content: "...")
        │
        ├─ promoted to staging   → prompt_environments row (prompt_id, version_id=v3, env=staging)
        └─ promoted to production → prompt_environments row (prompt_id, version_id=v2, env=production)
```

Resolution: `GET /api/prompts/summarize-text/resolve?env=production` returns version 2's compiled content (public, no auth).

## API conventions

**Envelope format:**
```json
{ "data": { ... }, "meta": { ... } }          // success (meta optional, used for pagination)
{ "error": { "message": "...", "code": "...", "details": {} } }  // error
```

**Auth header:** `Authorization: Bearer <token>`

**Pagination** (list endpoints): `meta.current_page`, `meta.last_page`, `meta.per_page`, `meta.total`

**HTTP status codes:** 200 OK, 201 Created, 202 Accepted, 204 No Content, 400 Bad Request, 401 Unauthorized, 403 Forbidden, 404 Not Found, 422 Unprocessable Entity, 500 Internal Server Error

## Job system

`RunTestJob` lives in `app/Jobs/RunTestJob.php`. It:
1. Marks the `TestRun` as `running`
2. Resolves the prompt version (development env → latest published → latest)
3. Compiles the prompt (replaces `{{var}}` placeholders)
4. Calls the configured LLM (OpenAI or Anthropic via `LLM_PROVIDER`)
5. Evaluates the response against `expected_output` using `assertion_type`
6. Saves `passed`/`failed`/`error` with response and evaluation data

**Monitor queue:**
```bash
docker compose exec app php artisan queue:monitor redis
docker compose logs -f queue
```

**Retry failed jobs:**
```bash
docker compose exec app php artisan queue:retry all
docker compose exec app php artisan queue:failed   # list failed jobs
```

## Adding a new assertion type

1. Add the value to the `assertion_type` enum in `database/migrations/*_create_test_cases_table.php` (and run `php artisan migrate` or add a separate alter migration).
2. Update the `TestCase` model's `$casts` to include the new value in the enum array (or use a string cast if using PHP enums).
3. In `app/Jobs/RunTestJob.php`, add a new case to the `evaluate()` method's match expression:
   ```php
   'my_new_type' => $this->evaluateMyNewType($response, $testCase->expected_output),
   ```
4. Implement the private method `evaluateMyNewType(string $response, string $expected): array` returning `['passed' => bool, 'reason' => string]`.
5. Update `StoreTestCaseRequest` validation rule for `assertion_type` to include the new value.
6. Add a test case in the seeder or write a feature test.

## Environment variables reference

| Key | Description |
|-----|-------------|
| `APP_KEY` | Laravel app encryption key — generate with `php artisan key:generate` |
| `DB_*` | PostgreSQL connection (host=`postgres` inside Docker) |
| `REDIS_HOST` | Redis host (`redis` inside Docker) |
| `QUEUE_CONNECTION` | Set to `redis` for async job processing |
| `LLM_PROVIDER` | `openai` or `anthropic` — selects which API to call in RunTestJob |
| `OPENAI_API_KEY` | OpenAI API key |
| `OPENAI_MODEL` | Defaults to `gpt-4o-mini` |
| `ANTHROPIC_API_KEY` | Anthropic API key |
| `ANTHROPIC_MODEL` | Defaults to `claude-haiku-3-5-20251001` |
| `SANCTUM_STATEFUL_DOMAINS` | Domains that can use session cookies — set to `localhost:5173` for local dev |
| `VITE_API_URL` | Frontend: base URL for Axios, e.g. `http://localhost:8000` |

## Roadmap

- **Webhook notifications on test failure** — POST to a configured URL when a test run status becomes `failed` or `error`
- **CLI tool (`promptops push/pull/run`)** — sync prompts from the registry to local files and trigger test runs from the terminal
- **GitHub Actions integration** — run test suite against a PR's prompt changes before merge
- **Team/workspace support with RBAC** — multi-tenant with owner/editor/viewer roles per workspace
- **Prompt marketplace / sharing** — export/import prompts as versioned bundles; public registry
- **Support for multi-turn prompt chains** — link prompts in sequence, passing outputs as inputs

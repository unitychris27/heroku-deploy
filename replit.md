# Workspace

## Overview

pnpm workspace monorepo using TypeScript. Each package manages its own dependencies.

## Stack

- **Monorepo tool**: pnpm workspaces
- **Node.js version**: 24
- **Package manager**: pnpm
- **TypeScript version**: 5.9
- **API framework**: Express 5
- **Database**: PostgreSQL + Drizzle ORM
- **Validation**: Zod (`zod/v4`), `drizzle-zod`
- **API codegen**: Orval (from OpenAPI spec)
- **Build**: esbuild (CJS bundle)
- **HTTP client**: axios (for Heroku Platform API)

## Key Commands

- `pnpm run typecheck` — full typecheck across all packages
- `pnpm run build` — typecheck + build all packages
- `pnpm --filter @workspace/api-spec run codegen` — regenerate API hooks and Zod schemas from OpenAPI spec
- `pnpm --filter @workspace/db run push` — push DB schema changes (dev only)
- `pnpm --filter @workspace/api-server run dev` — run API server locally

See the `pnpm-workspace` skill for workspace structure, TypeScript setup, and package details.

## Heroku Bot Deployment System

Automatically deploys WhatsApp bots to Heroku via the Heroku Platform API.

### Architecture

```
deploy.php / manage.php (cPanel PHP)
        │
        │  POST /api/external/deploy   (X-API-Key: API_SECRET_KEY)
        │  GET  /api/external/status/:jobId
        │  GET  /api/external/config/:appName
        │  PATCH /api/external/config/:appName
        │  POST /api/external/restart/:appName
        ▼
artifacts/api-server (Node.js / Express)
        │
        │  Heroku Platform API (bearer: HEROKU_API_KEY)
        ▼
   Heroku Apps (bot instances)
```

### Required Secrets

| Secret | Purpose |
|--------|---------|
| `HEROKU_API_KEY` | Authenticates all Heroku Platform API calls |
| `HEROKU_TEAM` | (optional) Creates apps under this Heroku team |
| `API_SECRET_KEY` | Shared secret between PHP files and Node.js external API |
| `DATABASE_URL` | PostgreSQL connection (used by lib/db) |

### Deployment Flow (end-to-end)

1. User visits `deploy.php` on cPanel → fills form → clicks deploy
2. PHP POSTs to `POST /api/external/deploy` with `X-API-Key` header
3. Node.js validates, creates in-memory job (status: `queued`), fires `runDeployment()` async
4. `runDeployment()` lifecycle:
   - `creating_app` → `heroku.createApp()` — creates Heroku app
   - `setting_buildpack` → sets stack (container or nodejs buildpack)
   - `setting_config` → sets all env vars on Heroku app
   - `deploying` → triggers source tarball build (`heroku.deploySource()`)
   - Polls build status every 4s (max 60 attempts = 4 min), retries twice on failure
   - `scaling` → scales web dyno to 1
   - `completed` → records `appUrl` and `logsUrl`
5. PHP polls `GET /api/external/status/:jobId` every few seconds
6. PHP updates its local MySQL DB with the final status

### Status Lifecycle

```
queued → creating_app → setting_buildpack → setting_config → deploying → scaling → completed
                                                                                  ↘ failed
```

### Supported Bots

| Key | Name | Stack | Source |
|-----|------|-------|--------|
| `cypherx` | CypherX | container | Dark-Xploit/CypherX |
| `bwm` | BWM-XMD | nodejs | Bwmxmd254/BWM-XMD-GO |
| `cypherxultra` | CypherX-Ultra | nodejs | Dark-Xploit/CypherX-Ultra |
| `kingmd` | King MD | container | sesco001/KING-MD |
| `anitav4` | Queen Anitah | nodejs | Blurnk/Anita-V4 |
| `atassa` | Atassa MD | nodejs | mauricegift/atassa |

### API Endpoints (all under `/api`)

#### Internal (no auth)
- `GET /api/healthz` — health check
- `GET /api/bots` — list bot types
- `POST /api/deploy` — deploy a bot
- `GET /api/deploy/status/:jobId` — poll job status
- `GET /api/deploy/jobs` — list all jobs
- `GET /api/logs/:appName` — get Heroku log session URL
- `GET /api/apps/:appName/config` — get Heroku config vars
- `PATCH /api/apps/:appName/config` — update config vars
- `POST /api/apps/:appName/restart` — restart dynos
- `DELETE /api/apps/:appName` — delete app

#### External (requires `X-API-Key` header or `?key=` query param)
- `POST /api/external/deploy` — deploy a bot (used by deploy.php)
- `GET /api/external/status/:jobId` — poll status (used by deploy.php)
- `GET /api/external/bots` — list bot types (used by PHP forms)
- `GET /api/external/config/:appName` — get config vars (used by manage.php)
- `PATCH /api/external/config/:appName` — update config vars (used by manage.php)
- `POST /api/external/restart/:appName` — restart dynos (used by manage.php)
- `GET /api/external/logs/:appName` — get log URL (used by manage.php)

### PHP Files

- `deploy.php` — cPanel page: bot selector form + deploy submission + polling + deployment history
- `manage.php` — cPanel page: config editor, restart, and log viewer for a deployed bot
- Both files use `$API_BASE_URL = "https://asset-manager2.replit.app/api"` (published URL)
- Both use `$API_SECRET = "Digitex2025"` which must match the `API_SECRET_KEY` secret

### File Structure

```
artifacts/api-server/src/
├── config/
│   └── bots.ts          # Bot registry + tarball URL builder
├── lib/
│   └── logger.ts        # Pino logger
├── services/
│   ├── heroku.ts        # Heroku Platform API: create/config/build/scale/logs/delete
│   └── queue.ts         # In-memory job map + auto-delete expired apps after 30 days
└── routes/
    ├── index.ts         # Route aggregator
    ├── health.ts        # GET /healthz
    ├── bots.ts          # GET /bots
    ├── deploy.ts        # POST /deploy, GET /deploy/status/:jobId, GET /deploy/jobs
    ├── logs.ts          # GET /logs/:appName
    ├── apps.ts          # GET/PATCH /apps/:appName/config, POST restart, DELETE
    └── external.ts      # /external/* routes with API key auth

artifacts/dashboard/src/
├── pages/home.tsx       # Main page layout
└── components/
    ├── DeployForm.tsx   # Bot type + field form → triggers deployment
    ├── JobTracker.tsx   # Live status polling for active deployment
    ├── JobsList.tsx     # Deployment history table with manage/delete actions
    ├── LogsViewer.tsx   # Heroku log stream fetcher
    └── ConfigEditor.tsx # Edit Heroku config vars for deployed app

lib/
├── api-spec/openapi.yaml        # Source of truth for API types
├── api-client-react/            # Generated TanStack Query hooks (from OpenAPI)
├── api-zod/                     # Generated Zod schemas (from OpenAPI)
└── db/                          # Drizzle ORM + PostgreSQL schema

Root (cPanel integration):
├── deploy.php                   # Bot deployment page for cPanel users
├── manage.php                   # Bot management page for cPanel users
└── vps-setup.sh                 # VPS provisioning script (Ubuntu/Nginx/PM2)
```

# heroku-deploy — Bot Deploy API

A self-hosted Heroku deployment manager for WhatsApp MD bots.  
Supports **9 bot types**: CypherX, BWM-XMD, King MD, Queen Anitah, Atassa MD, CypherX-Ultra, Keith MD, June Ultra, Silent Wolf.

---

## Architecture

```
heroku-deploy/
├── artifacts/bot-deploy-api/    # Express API server (Node.js 20, esbuild)
│   ├── src/
│   │   ├── app.ts               # Express app — BASE_PATH mount
│   │   ├── index.ts             # HTTP server entry point
│   │   ├── config/bots.ts       # Bot definitions & field schemas (9 bots)
│   │   ├── routes/
│   │   │   ├── health.ts        # GET /api/healthz
│   │   │   ├── external.ts      # POST /api/external/deploy, GET /api/external/bots
│   │   │   ├── deploy.ts        # POST /api/deploy (internal form)
│   │   │   ├── apps.ts          # GET/PATCH/DELETE /api/apps/:name
│   │   │   ├── admin.ts         # PATCH /api/admin/settings
│   │   │   └── logs.ts          # GET /api/logs/:appName
│   │   └── services/
│   │       ├── heroku.ts        # Heroku Platform API wrapper
│   │       ├── queue.ts         # In-memory deploy job queue
│   │       ├── settings.ts      # Persistent settings (data/bot-deploy-settings.json)
│   │       └── appRegistry.ts   # In-memory app → API key registry
│   └── dist/                    # esbuild output (index.mjs + pino workers)
├── deploy.php                   # cPanel PHP frontend — deploy form
├── manage.php                   # cPanel PHP frontend — manage bots
├── admin-settings.php           # Admin settings panel
├── Dockerfile                   # Docker build (2-stage, node:20-alpine)
├── docker-compose.yml           # Docker Compose service definition
├── nginx.conf                   # nginx reverse proxy template
└── pm2-prod.config.cjs          # PM2 config for bare-metal VPS / cPanel
```

---

## Environment Variables

| Variable | Required | Description |
|----------|----------|-------------|
| `PORT` | ✅ | Port the HTTP server listens on (default: `8097`) |
| `HEROKU_API_KEY` | ✅ | Heroku Platform API key — get from `heroku auth:token` |
| `API_SECRET_KEY` | ✅ | Shared secret for `X-API-Key` auth on protected endpoints |
| `BASE_PATH` | optional | URL prefix when running behind a sub-path proxy (e.g. `/heroku-deploy`). Leave **empty** when nginx proxies at root (`api.yourdomain.com → /`) |
| `NODE_ENV` | optional | `production` or `development` (default: `production`) |

Create `.env` from the example:

```bash
cp .env.example .env
nano .env
```

`.env.example`:
```env
HEROKU_API_KEY=HRKU-xxxxxxxxxxxxxxxxxxxx
API_SECRET_KEY=change-me-to-a-strong-secret
PORT=8097
BASE_PATH=
NODE_ENV=production
```

---

## Deployment Options

### Option A — Docker + nginx (Recommended for VPS / Hyperlift)

Mirrors exactly how the API runs in the Replit hosted environment.

```bash
# 1. Clone
git clone https://github.com/unitychris27/heroku-deploy.git
cd heroku-deploy

# 2. Configure
cp .env.example .env
nano .env           # set HEROKU_API_KEY and API_SECRET_KEY

# 3. Build and start
docker compose up -d --build

# 4. Verify
curl http://localhost:8097/api/healthz
# → {"status":"ok"}

# 5. Check all 9 bots are loaded
curl http://localhost:8097/api/external/bots | python3 -m json.tool
```

**Then set up nginx** (copy `nginx.conf`, replace `api.yourdomain.com`, run Certbot):

```bash
sudo cp nginx.conf /etc/nginx/sites-available/api.yourdomain.com
# Edit: replace api.yourdomain.com with your actual domain
sudo nano /etc/nginx/sites-available/api.yourdomain.com

sudo ln -s /etc/nginx/sites-available/api.yourdomain.com \
           /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx

# SSL (free Let's Encrypt cert)
sudo certbot --nginx -d api.yourdomain.com
```

Your API will be live at `https://api.yourdomain.com/api/healthz`.

---

### Option B — Docker (manual, without Compose)

```bash
docker build -t heroku-deploy .
docker run -d \
  --name bot-deploy-api \
  --restart unless-stopped \
  -p 8097:8097 \
  --env-file .env \
  -v $(pwd)/data:/app/data \
  heroku-deploy
```

---

### Option C — PM2 on bare-metal VPS / cPanel (no Docker)

Requires Node.js ≥ 20 and pnpm 10.x.

```bash
# Install pnpm if needed
corepack enable && corepack prepare pnpm@10.33.0 --activate

# Install deps (bot-deploy-api only)
pnpm install --filter @workspace/bot-deploy-api...

# Build
node artifacts/bot-deploy-api/build.mjs

# Copy env
cp .env.example .env && nano .env

# Start with PM2
pm2 start pm2-prod.config.cjs
pm2 save
pm2 startup      # enable auto-start on reboot
```

---

### Option D — Replit (cloud-hosted)

The API is already set up as a Replit artifact at `/heroku-deploy`. In Replit:

- **BASE_PATH** = `/heroku-deploy` (sub-path proxy routing)
- **PORT** = `8098` (assigned by Replit)
- Accessible at: `https://<repl-url>/heroku-deploy/api/healthz`

The Replit dev command bakes in these values:
```json
"dev": "PORT=8098 BASE_PATH=/heroku-deploy node ./build.mjs && PORT=8098 BASE_PATH=/heroku-deploy node --enable-source-maps ./dist/index.mjs"
```

For Docker/VPS: `BASE_PATH` is empty and `PORT` is `8097` — the API mounts routes at `/api/...` directly.

---

## API Endpoints

> **Base URL** varies by deployment:
> - Docker/VPS: `https://api.yourdomain.com`
> - Replit: `https://<repl>.replit.dev/heroku-deploy`

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| `GET` | `<base>/api/healthz` | none | Health check |
| `GET` | `<base>/api/external/bots` | none | List all bot types & fields |
| `POST` | `<base>/api/external/deploy` | `X-API-Key` | Deploy a bot to Heroku |
| `GET` | `<base>/api/external/status/:jobId` | `X-API-Key` | Poll deploy status |
| `GET` | `<base>/api/external/config/:app` | `X-API-Key` | Get Heroku app config vars |
| `PATCH` | `<base>/api/external/config/:app` | `X-API-Key` | Update config vars |
| `POST` | `<base>/api/external/restart/:app` | `X-API-Key` | Restart dynos |
| `DELETE` | `<base>/api/external/delete/:app` | `X-API-Key` | Delete Heroku app |
| `GET` | `<base>/api/external/check/:app` | `X-API-Key` | Check real Heroku status |
| `GET` | `<base>/api/admin/settings` | `X-API-Key` | View settings |
| `PATCH` | `<base>/api/admin/settings` | `X-API-Key` | Update API keys |
| `POST` | `<base>/api/admin/test-connection` | `X-API-Key` | Test Heroku key |

**Authentication** — pass your `API_SECRET_KEY` as:
```
X-API-Key: your-secret-key
# or
Authorization: Bearer your-secret-key
# or
?key=your-secret-key
```

---

## Deploy a Bot — Example

```bash
curl -X POST https://api.yourdomain.com/api/external/deploy \
  -H "Content-Type: application/json" \
  -H "X-API-Key: your-secret-key" \
  -d '{
    "botType": "bwm",
    "appName": "my-whatsapp-bot",
    "botVars": {
      "session": "YOUR_SESSION_STRING",
      "ownerNumber": "254700000000"
    }
  }'

# Poll until status = "completed"
curl -H "X-API-Key: your-secret-key" \
  https://api.yourdomain.com/api/external/status/my-whatsapp-bot-1234567890
```

---

## Supported Bots

| Key | Name | Repo |
|-----|------|------|
| `cypherx` | CypherX | TristanCage/CypherX |
| `bwm` | BWM-XMD | Bwmxmd254/BWM-XMD-GO |
| `cypherxultra` | CypherX-Ultra | Dark-Xploit/CypherX-Ultra |
| `kingmd` | King MD | sesco001/KING-MD |
| `anitav4` | Queen Anitah | Blurnk/Anita-V4 |
| `atassa` | Atassa MD | mauricegift/atassa |
| `keithmd` | Keith MD | Keith-web3/Keith-MD |
| `juneultra` | June Ultra | june-lang/june-ultra |
| `silentwolf` | Silent Wolf | silent-wolf-dev/silent-wolf |

---

## PHP Frontend Configuration

The PHP panel (`deploy.php`, `manage.php`, `admin-settings.php`) connects to the API.  
Edit `config.php`:

```php
define('API_BASE_URL', 'https://api.yourdomain.com');  // no trailing slash
define('API_SECRET_KEY', 'your-secret-key');
define('ADMIN_PASSWORD', 'your-admin-password');
```

---

## Development

```bash
# Install all workspace deps
pnpm install

# Run bot-deploy API in dev mode (rebuilds on each start)
pnpm --filter @workspace/bot-deploy-api run dev

# Typecheck
pnpm --filter @workspace/bot-deploy-api run typecheck

# Build production bundle
node artifacts/bot-deploy-api/build.mjs
```

---

## License

MIT — © Digitex 2025

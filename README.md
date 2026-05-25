# heroku-deploy — Bot Deploy API

A self-hosted Heroku deployment manager for WhatsApp MD bots.  
Supports **9 bot types**: CypherX, BWM-XMD, King MD, Queen Anitah, Atassa MD, CypherX-Ultra, Keith MD, June Ultra, Silent Wolf.

---

## Architecture

```
heroku-deploy/
├── artifacts/api-server/        # Express API (Node.js 20)
│   ├── src/
│   │   ├── app.ts               # Express app setup
│   │   ├── config/bots.ts       # Bot definitions & field schemas
│   │   ├── routes/
│   │   │   ├── external.ts      # GET /api/external/bots
│   │   │   ├── deploy.ts        # POST /api/deploy
│   │   │   ├── apps.ts          # GET/DELETE /api/apps/:id
│   │   │   ├── health.ts        # GET /api/healthz
│   │   │   └── ...
│   │   └── services/
│   │       ├── heroku.ts        # Heroku Platform API wrapper
│   │       └── queue.ts         # Deploy job queue
│   └── dist/                    # Compiled output (esbuild)
├── deploy.php                   # cPanel PHP frontend — deploy form
├── manage.php                   # cPanel PHP frontend — manage bots
├── admin-settings.php           # Admin settings panel
├── login.php / logout.php       # Session auth
├── pm2-prod.config.cjs          # PM2 config for VPS/cPanel
└── Dockerfile                   # Docker alternative deployment
```

---

## Quick Start (Docker)

```bash
# 1. Clone
git clone https://github.com/unitychris27/heroku-deploy.git
cd heroku-deploy

# 2. Configure environment
cp .env.example .env
nano .env          # set API_SECRET_KEY and HEROKU_API_KEY

# 3. Build & run
docker build -t heroku-deploy .
docker run -d \
  --name heroku-deploy \
  --restart unless-stopped \
  -p 8097:8097 \
  --env-file .env \
  heroku-deploy

# 4. Verify
curl http://localhost:8097/api/healthz
# → {"status":"ok"}
```

---

## Deployment Options

### Option A — Docker (Recommended)

Requires Docker on any VPS or cPanel server with Docker support.

```bash
docker compose up -d          # uses docker-compose.yml
```

Or manually:

```bash
docker build -t heroku-deploy .
docker run -d --name heroku-deploy --restart unless-stopped \
  -p 8097:8097 --env-file .env heroku-deploy
```

### Option B — PM2 on VPS / cPanel

Requires Node.js ≥ 20 and pnpm.

```bash
# Install dependencies
pnpm install --filter @workspace/api-server

# Build
node artifacts/api-server/build.mjs

# Start with PM2
pm2 start pm2-prod.config.cjs
pm2 save
pm2 startup          # enable @reboot auto-start
```

### Option C — cPanel Shared Hosting (PHP frontend only)

Upload the PHP files to your public_html or a subdomain directory:

```
deploy.php
manage.php
admin-settings.php
login.php
logout.php
config.php
includes/
```

Point the PHP frontend at your running API (Option A or B) by setting `API_BASE_URL` in `config.php`.

---

## Environment Variables

| Variable | Required | Description |
|----------|----------|-------------|
| `API_SECRET_KEY` | ✅ | Shared secret for PHP ↔ API auth (e.g. `Digitex2025`) |
| `HEROKU_API_KEY` | ✅ | Heroku Platform API key — get from `heroku auth:token` |
| `PORT` | optional | API listen port (default: `8097`) |
| `NODE_ENV` | optional | `production` or `development` |

Create `.env` from the example:

```bash
cp .env.example .env
```

`.env.example`:
```env
API_SECRET_KEY=change-me
HEROKU_API_KEY=HRKU-xxxxxxxxxxxxxxxxxxxx
PORT=8097
NODE_ENV=production
```

---

## API Endpoints

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| `GET` | `/api/healthz` | none | Health check |
| `GET` | `/api/external/bots` | none | List all supported bot types & fields |
| `POST` | `/api/deploy` | `x-api-key` | Deploy a bot to Heroku |
| `GET` | `/api/apps` | `x-api-key` | List deployed bot apps |
| `GET` | `/api/apps/:id` | `x-api-key` | Get a single app |
| `DELETE` | `/api/apps/:id` | `x-api-key` | Delete a Heroku app |
| `GET` | `/api/apps/:id/logs` | `x-api-key` | Stream app logs |

**Authentication:** Pass your `API_SECRET_KEY` as the `x-api-key` header on protected routes.

```bash
curl -H "x-api-key: Digitex2025" http://localhost:8097/api/apps
```

---

## Supported Bots

| Bot | Description |
|-----|-------------|
| CypherX | Multi-device WhatsApp bot |
| BWM-XMD | Feature-rich MD bot |
| King MD | Royal commands bot |
| Queen Anitah | Anitah MD variant |
| Atassa MD | Atassa fork |
| CypherX-Ultra | Enhanced CypherX |
| Keith MD | Keith's MD fork |
| June Ultra | June Ultra edition |
| Silent Wolf | Silent Wolf bot |

---

## Reverse Proxy Setup (nginx)

Point `api.yourdomain.com` at the running container/PM2 process:

```nginx
server {
    listen 80;
    server_name api.yourdomain.com;

    location / {
        proxy_pass         http://127.0.0.1:8097;
        proxy_http_version 1.1;
        proxy_set_header   Host $host;
        proxy_set_header   X-Real-IP $remote_addr;
        proxy_set_header   X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header   X-Forwarded-Proto $scheme;
    }
}
```

Then enable SSL with Certbot:

```bash
certbot --nginx -d api.yourdomain.com
```

---

## PHP Frontend Configuration

Edit `config.php` to point the PHP panel at your API:

```php
define('API_BASE_URL', 'https://api.yourdomain.com');
define('API_SECRET_KEY', 'your-secret-key');
define('ADMIN_PASSWORD', 'your-admin-password');
```

---

## Development

```bash
# Install all workspace dependencies
pnpm install

# Run API in dev mode (hot reload)
pnpm --filter @workspace/api-server run dev

# Typecheck
pnpm run typecheck

# Build for production
node artifacts/api-server/build.mjs
```

---

## License

MIT — © Digitex 2025

# Bot Deployment System

A full-stack system for deploying and managing WhatsApp bots. It consists of a Node.js API server (runs on Replit **or any VPS**) and PHP integration files that you upload to any cPanel-hosted website.

---

## Architecture

```
cPanel Site (PHP)                API Server (Node.js)              Platform
  deploy.php        ──────────►  /api/external/deploy       ──────►  Create & build bot app
  manage.php        ──────────►  /api/external/*             ──────►  Config / Restart / Logs / Delete
  admin-settings.php ─────────►  /api/admin/settings         ──────►  Store API key & team securely
```

---

## Supported Bot Types

| Key | Name |
|-----|------|
| `cypherx` | CypherX |
| `bwm` | BWM-XMD |
| `cypherxultra` | CypherX-Ultra |
| `kingmd` | King MD |
| `anitav4` | Queen Anitah |
| `atassa` | Atassa MD |

---

## Quick Setup on Replit (already running)

1. Set the following Replit Secrets:
   - `API_SECRET_KEY` — shared secret (e.g. `Digitex2025`)
   - `HEROKU_API_KEY` — your platform API key
   - `HEROKU_TEAM` — team/org name (optional)

2. Upload `deploy.php`, `manage.php`, and `admin-settings.php` to your cPanel site.

3. Edit the config block at the top of each file:
   ```php
   $API_BASE_URL = "https://your-app.replit.app/api";
   $API_SECRET   = "your-secret-key";
   ```

4. Open `admin-settings.php` in your browser to set and verify credentials.

---

## VPS Installation Guide (Ubuntu 22.04 / 24.04)

### Requirements

| Tool | Version |
|------|---------|
| Node.js | 20 or higher |
| pnpm | 8 or higher |
| PM2 | latest (process manager) |
| Nginx | optional (recommended for HTTPS) |

---

### Step 1 — Connect to your VPS and clone the repo

```bash
ssh root@YOUR_VPS_IP

# Install Git if not present
apt-get update && apt-get install -y git

# Clone the repository
git clone https://github.com/Digitex-Softwares/heroku-deploy.git /opt/heroku-deploy
cd /opt/heroku-deploy
```

---

### Step 2 — Install Node.js and pnpm

```bash
# Install Node.js 20 via NodeSource
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
apt-get install -y nodejs

# Install pnpm
npm install -g pnpm

# Verify
node -v    # should print v20.x.x
pnpm -v    # should print 8.x.x or higher
```

---

### Step 3 — Install dependencies and build

```bash
cd /opt/heroku-deploy
pnpm install
pnpm --filter @workspace/api-server run build
```

---

### Step 4 — Create the environment file

```bash
cp .env.example artifacts/api-server/.env
nano artifacts/api-server/.env
```

Fill in your values:

```env
PORT=8080
API_SECRET_KEY=your-strong-secret-here
HEROKU_API_KEY=your-platform-api-key
HEROKU_TEAM=your-team-name         # optional — leave blank for personal account
NODE_ENV=production
```

> **Tip:** You can leave `HEROKU_API_KEY` and `HEROKU_TEAM` blank and set them later via the Admin Settings panel in your PHP dashboard.

---

### Step 5 — Start with PM2

```bash
# Install PM2 globally
npm install -g pm2

# Create logs directory
mkdir -p /opt/heroku-deploy/artifacts/api-server/logs

# Start the API server
cd /opt/heroku-deploy
pm2 start ecosystem.config.cjs

# Save PM2 process list and enable startup on reboot
pm2 save
pm2 startup
# Follow the command printed by pm2 startup (copy and run it)
```

**PM2 commands:**
```bash
pm2 status                     # view all processes
pm2 logs bot-deploy-api        # live logs
pm2 restart bot-deploy-api     # restart
pm2 reload bot-deploy-api      # zero-downtime reload
```

---

### Step 6 — Open firewall port

```bash
# UFW (Ubuntu/Debian)
ufw allow 8080/tcp
ufw enable

# firewalld (CentOS/RHEL)
firewall-cmd --permanent --add-port=8080/tcp && firewall-cmd --reload
```

Test that the server is reachable:
```bash
curl http://YOUR_VPS_IP:8080/api/health
# Should return: {"status":"ok"}
```

---

### Step 7 — (Recommended) Nginx reverse proxy + HTTPS

Create an Nginx config file:

```bash
nano /etc/nginx/sites-available/bot-api
```

Paste:

```nginx
server {
    listen 80;
    server_name api.yourdomain.com;   # or _ for any hostname / IP

    location / {
        proxy_pass         http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header   Upgrade $http_upgrade;
        proxy_set_header   Connection 'upgrade';
        proxy_set_header   Host $host;
        proxy_set_header   X-Real-IP $remote_addr;
        proxy_set_header   X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_cache_bypass $http_upgrade;
        proxy_read_timeout 120s;
    }
}
```

Enable the site:

```bash
ln -s /etc/nginx/sites-available/bot-api /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

Enable HTTPS with Certbot:

```bash
apt-get install -y certbot python3-certbot-nginx
certbot --nginx -d api.yourdomain.com
```

Now your API is available at `https://api.yourdomain.com/api`.

---

### Step 8 — Upload PHP files to cPanel

Upload these three files to your cPanel site:
- `deploy.php`
- `manage.php`
- `admin-settings.php`

Edit the config block at the top of **each file**:

```php
// Using a domain (with Nginx + HTTPS):
$API_BASE_URL = "https://api.yourdomain.com/api";

// Or using IP + port directly (no Nginx):
$API_BASE_URL = "http://YOUR_VPS_IP:8080/api";

$API_SECRET = "your-strong-secret-here";  // must match API_SECRET_KEY in .env
```

---

### Step 9 — Configure via Admin Panel

Open `admin-settings.php` in your browser. Use it to:
- Enter your platform API key (stored securely on the server)
- Set your team/organization name
- Test the connection to verify everything works
- Update the API secret key if needed

---

## Updating the Code on VPS

```bash
cd /opt/heroku-deploy
git pull origin main
pnpm install
pnpm --filter @workspace/api-server run build
pm2 restart bot-deploy-api
```

---

## API Endpoints

All endpoints (except `/api/health`) require authentication:
- Header: `X-API-Key: <your-secret-key>`
- Header: `Authorization: Bearer <your-secret-key>`
- Query param: `?key=<your-secret-key>`

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/health` | Health check (no auth required) |
| `POST` | `/api/external/deploy` | Deploy a new bot |
| `GET` | `/api/external/status/:jobId` | Poll deployment status |
| `GET` | `/api/external/bots` | List supported bot types |
| `GET` | `/api/external/config/:appName` | Get bot config vars |
| `PATCH` | `/api/external/config/:appName` | Update bot config vars |
| `POST` | `/api/external/restart/:appName` | Restart bot |
| `GET` | `/api/external/logs/:appName` | Get bot logs |
| `DELETE` | `/api/external/delete/:appName` | Delete bot permanently |
| `GET` | `/api/external/check/:appName` | Check live bot status |
| `GET` | `/api/admin/settings` | View current settings (masked) |
| `PATCH` | `/api/admin/settings` | Update settings |
| `POST` | `/api/admin/test-connection` | Test platform connection |

---

## Security Notes

- Never commit `.env` or `data/settings.json` to git — both are in `.gitignore`.
- Use HTTPS in production (Nginx + Certbot as shown above).
- Keep your API key and secret key private and rotate them if ever exposed.
- The Admin Settings panel is protected by the same secret key as all other endpoints.

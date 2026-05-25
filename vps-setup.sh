#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# HerokuDeploy VPS Setup Script
# Tested on Ubuntu 22.04 LTS / 24.04 LTS
# Run as root or with sudo
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail

# ── Configuration — edit these before running ─────────────────────────────────
REPO_URL="git@github.com:Digitex-Softwares/heroku-deploy.git"
APP_DIR="/opt/herokudeploy"
APP_PORT=8080
NODE_VERSION=20

# Secrets — fill in before running (or export them in the environment)
HEROKU_API_KEY="${HEROKU_API_KEY:-}"
HEROKU_TEAM="${HEROKU_TEAM:-}"
SESSION_SECRET="${SESSION_SECRET:-$(openssl rand -hex 32)}"
API_SECRET_KEY="${API_SECRET_KEY:-Digitex2025}"
# ─────────────────────────────────────────────────────────────────────────────

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; NC='\033[0m'
info()  { echo -e "${GREEN}[INFO]${NC}  $*"; }
warn()  { echo -e "${YELLOW}[WARN]${NC}  $*"; }
error() { echo -e "${RED}[ERROR]${NC} $*"; exit 1; }

[[ $EUID -ne 0 ]] && error "Run this script as root or with sudo."
[[ -z "$HEROKU_API_KEY" ]] && error "Set HEROKU_API_KEY before running (export HEROKU_API_KEY=xxx)."
[[ -z "$HEROKU_TEAM"    ]] && warn  "HEROKU_TEAM is empty — deployments will use your personal account."

# ── 1. System packages ────────────────────────────────────────────────────────
info "Updating system packages..."
apt-get update -qq
apt-get install -y -qq curl git nginx openssl ca-certificates gnupg 2>/dev/null

# ── 2. Node.js ────────────────────────────────────────────────────────────────
if ! command -v node &>/dev/null || [[ $(node -e "process.stdout.write(process.versions.node.split('.')[0])") -lt $NODE_VERSION ]]; then
    info "Installing Node.js $NODE_VERSION..."
    mkdir -p /etc/apt/keyrings
    curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key \
        | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg
    echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_${NODE_VERSION}.x nodistro main" \
        > /etc/apt/sources.list.d/nodesource.list
    apt-get update -qq
    apt-get install -y -qq nodejs
else
    info "Node.js $(node --version) already installed."
fi

# ── 3. pnpm ───────────────────────────────────────────────────────────────────
if ! command -v pnpm &>/dev/null; then
    info "Installing pnpm..."
    npm install -g pnpm --silent
fi

# ── 4. PM2 ────────────────────────────────────────────────────────────────────
if ! command -v pm2 &>/dev/null; then
    info "Installing PM2..."
    npm install -g pm2 --silent
fi

# ── 5. Clone or update repository ────────────────────────────────────────────
if [[ -d "$APP_DIR/.git" ]]; then
    info "Updating repository at $APP_DIR..."
    git -C "$APP_DIR" pull --ff-only
else
    info "Cloning repository to $APP_DIR..."
    git clone "$REPO_URL" "$APP_DIR"
fi

# ── 6. Environment file ───────────────────────────────────────────────────────
info "Writing environment file..."
cat > "$APP_DIR/artifacts/api-server/.env" <<ENV
NODE_ENV=production
PORT=$APP_PORT
HEROKU_API_KEY=$HEROKU_API_KEY
HEROKU_TEAM=$HEROKU_TEAM
SESSION_SECRET=$SESSION_SECRET
API_SECRET_KEY=$API_SECRET_KEY
ENV
chmod 600 "$APP_DIR/artifacts/api-server/.env"

# ── 7. Install dependencies and build ────────────────────────────────────────
info "Installing dependencies..."
cd "$APP_DIR"
pnpm install --frozen-lockfile --silent

info "Building API server..."
pnpm --filter @workspace/api-server run build

# ── 8. PM2 process ───────────────────────────────────────────────────────────
PM2_APP="herokudeploy-api"
info "Configuring PM2 process ($PM2_APP)..."

cat > "$APP_DIR/pm2.config.cjs" <<PM2
module.exports = {
  apps: [{
    name: "$PM2_APP",
    cwd: "$APP_DIR/artifacts/api-server",
    script: "dist/index.js",
    interpreter: "node",
    env: {
      NODE_ENV: "production",
      PORT: $APP_PORT
    },
    env_file: "$APP_DIR/artifacts/api-server/.env",
    instances: 1,
    autorestart: true,
    watch: false,
    max_memory_restart: "512M",
    error_file: "/var/log/herokudeploy/api-error.log",
    out_file:   "/var/log/herokudeploy/api-out.log",
    log_date_format: "YYYY-MM-DD HH:mm:ss Z"
  }]
};
PM2

mkdir -p /var/log/herokudeploy

if pm2 describe "$PM2_APP" &>/dev/null; then
    pm2 reload "$PM2_APP" --update-env
else
    pm2 start "$APP_DIR/pm2.config.cjs"
fi
pm2 save
pm2 startup systemd -u root --hp /root | tail -1 | bash || true

# ── 9. Nginx reverse proxy ────────────────────────────────────────────────────
info "Configuring Nginx..."
DOMAIN="${VPS_DOMAIN:-_}"

cat > /etc/nginx/sites-available/herokudeploy <<NGINX
server {
    listen 80;
    server_name $DOMAIN;

    client_max_body_size 10M;

    location /api {
        proxy_pass         http://127.0.0.1:$APP_PORT;
        proxy_http_version 1.1;
        proxy_set_header   Upgrade \$http_upgrade;
        proxy_set_header   Connection keep-alive;
        proxy_set_header   Host \$host;
        proxy_set_header   X-Real-IP \$remote_addr;
        proxy_set_header   X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header   X-Forwarded-Proto \$scheme;
        proxy_read_timeout 300s;
        proxy_send_timeout 300s;
    }

    # Health check
    location /health {
        proxy_pass http://127.0.0.1:$APP_PORT/health;
    }
}
NGINX

ln -sf /etc/nginx/sites-available/herokudeploy /etc/nginx/sites-enabled/herokudeploy
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx

# ── 10. Firewall ──────────────────────────────────────────────────────────────
if command -v ufw &>/dev/null; then
    info "Configuring UFW firewall..."
    ufw allow ssh  >/dev/null 2>&1 || true
    ufw allow http >/dev/null 2>&1 || true
    ufw --force enable >/dev/null 2>&1 || true
fi

# ── Done ──────────────────────────────────────────────────────────────────────
info "Setup complete."
echo ""
echo "  API server running on http://127.0.0.1:$APP_PORT"
echo "  Nginx proxying /api and /health to the API"
echo "  PM2 process: $PM2_APP"
echo "  Logs: /var/log/herokudeploy/"
echo ""
echo "  To update the application later:"
echo "    cd $APP_DIR && git pull && pnpm install && pnpm --filter @workspace/api-server run build && pm2 reload $PM2_APP"
echo ""
if [[ "$DOMAIN" == "_" ]]; then
    warn "Set VPS_DOMAIN=yourdomain.com before running to configure the correct Nginx server_name."
    warn "For HTTPS, run: apt-get install certbot python3-certbot-nginx && certbot --nginx -d yourdomain.com"
fi

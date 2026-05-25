# syntax=docker/dockerfile:1
# ─── Stage 1: build ──────────────────────────────────────────────────────────
FROM node:20-alpine AS builder

# Pin pnpm to exact version matching the lockfile (pnpm 11.x requires Node >=22)
RUN corepack enable && corepack prepare pnpm@10.33.0 --activate

WORKDIR /app

# Copy workspace manifests (layer-cache friendly — only reinstall on changes)
COPY pnpm-workspace.yaml package.json ./
COPY pnpm-lock.yaml* ./
COPY lib/api-zod/package.json          lib/api-zod/
COPY lib/api-client-react/package.json lib/api-client-react/
COPY lib/api-spec/package.json         lib/api-spec/
COPY lib/db/package.json               lib/db/
COPY scripts/package.json              scripts/
COPY artifacts/bot-deploy-api/package.json artifacts/bot-deploy-api/

# Install — frozen so the image always matches pnpm-lock.yaml
RUN pnpm install --frozen-lockfile --filter @workspace/bot-deploy-api...

# Copy the bot-deploy-api source
COPY artifacts/bot-deploy-api/ artifacts/bot-deploy-api/
COPY tsconfig.base.json ./
COPY tsconfig.json ./

# Build the esbuild bundle
RUN node artifacts/bot-deploy-api/build.mjs

# ─── Stage 2: runtime ────────────────────────────────────────────────────────
FROM node:20-alpine AS runtime

# tini: proper PID 1 / signal handling
RUN apk add --no-cache tini

WORKDIR /app

# Copy only the compiled bundle
COPY --from=builder /app/artifacts/bot-deploy-api/dist ./dist

# Create a non-root user
RUN addgroup -S botdeploy && adduser -S botdeploy -G botdeploy
USER botdeploy

# Runtime config
# PORT        — port the HTTP server listens on (default: 8097)
# BASE_PATH   — URL prefix if running behind a sub-path reverse proxy (leave
#               empty when nginx proxies at root, e.g. api.yourdomain.com → /)
# HEROKU_API_KEY  — Heroku Platform API key
# API_SECRET_KEY  — shared secret for X-API-Key auth on protected endpoints
ENV NODE_ENV=production \
    PORT=8097 \
    BASE_PATH=""

EXPOSE 8097

# Create data dir for persistent settings
RUN mkdir -p /app/data && chown botdeploy:botdeploy /app/data

ENTRYPOINT ["/sbin/tini", "--"]
CMD ["node", "--enable-source-maps", "dist/index.mjs"]

HEALTHCHECK --interval=30s --timeout=5s --start-period=15s --retries=3 \
  CMD wget -qO- "http://localhost:${PORT}/api/healthz" | grep -q '"status":"ok"' || exit 1

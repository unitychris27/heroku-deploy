# syntax=docker/dockerfile:1
# ─── Stage 1: build ──────────────────────────────────────────────────────────
FROM node:20-alpine AS builder

WORKDIR /app

# Copy manifest first for layer-cache
COPY artifacts/bot-deploy-api/package.json ./package.json

# npm install (no pnpm workspace needed — esbuild handles TS compilation)
RUN npm install --legacy-peer-deps

# Copy source files
COPY artifacts/bot-deploy-api/src/      ./src/
COPY artifacts/bot-deploy-api/build.mjs ./build.mjs

# Compile via esbuild → dist/index.mjs
RUN node build.mjs

# ─── Stage 2: runtime ────────────────────────────────────────────────────────
FROM node:20-alpine AS runtime

RUN apk add --no-cache tini

WORKDIR /app

# Copy only the compiled bundle from builder
COPY --from=builder /app/dist ./dist

# Non-root user
RUN addgroup -S botdeploy && adduser -S botdeploy -G botdeploy && \
    mkdir -p /app/data && chown -R botdeploy:botdeploy /app

USER botdeploy

# Runtime config
# PORT=8080 matches Hyperlift's default application port expectation.
# Override via Hyperlift env vars if needed.
# BASE_PATH — URL prefix when behind a sub-path proxy (leave empty for root)
# HEROKU_API_KEY  — Heroku Platform API key
# API_SECRET_KEY  — shared secret for X-API-Key auth
ENV NODE_ENV=production \
    PORT=8080 \
    BASE_PATH=""

EXPOSE 8080

ENTRYPOINT ["/sbin/tini", "--"]
CMD ["node", "--enable-source-maps", "dist/index.mjs"]

HEALTHCHECK --interval=30s --timeout=5s --start-period=15s --retries=3 \
  CMD wget -qO- "http://localhost:8080/api/healthz" | grep -q '"status":"ok"' || exit 1

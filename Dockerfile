# syntax=docker/dockerfile:1
# ─── Stage 1: build ──────────────────────────────────────────────────────────
FROM node:20-alpine AS builder

# Pin pnpm to the exact version the lockfile was generated with
RUN corepack enable && corepack prepare pnpm@10.33.0 --activate

WORKDIR /app

# Copy workspace manifests first (layer-cache friendly)
COPY pnpm-workspace.yaml package.json ./
COPY pnpm-lock.yaml* ./

COPY lib/api-zod/package.json          lib/api-zod/
COPY lib/api-client-react/package.json lib/api-client-react/
COPY lib/api-spec/package.json         lib/api-spec/
COPY lib/db/package.json               lib/db/
COPY artifacts/api-server/package.json artifacts/api-server/
COPY scripts/package.json              scripts/

# Install all dependencies (frozen — must match pnpm-lock.yaml)
RUN pnpm install --frozen-lockfile

# Copy full source
COPY . .

# Build the API server bundle
RUN node artifacts/api-server/build.mjs

# ─── Stage 2: runtime ────────────────────────────────────────────────────────
FROM node:20-alpine AS runtime

RUN apk add --no-cache tini

WORKDIR /app

# Copy compiled bundle and runtime manifest
COPY --from=builder /app/artifacts/api-server/dist       ./dist
COPY --from=builder /app/artifacts/api-server/package.json ./package.json

# Install production-only deps
RUN npm install --omit=dev --ignore-scripts 2>/dev/null || true

# Create non-root user
RUN addgroup -S botdeploy && adduser -S botdeploy -G botdeploy
USER botdeploy

ENV NODE_ENV=production \
    PORT=8097

EXPOSE 8097

ENTRYPOINT ["/sbin/tini", "--"]
CMD ["node", "dist/index.mjs"]

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
  CMD wget -qO- http://localhost:8097/api/healthz | grep -q '"status":"ok"' || exit 1

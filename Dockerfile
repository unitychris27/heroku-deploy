# syntax=docker/dockerfile:1
# ─── Stage 1: build ──────────────────────────────────────────────────────────
FROM node:20-alpine AS builder

# Install pnpm
RUN corepack enable && corepack prepare pnpm@latest --activate

WORKDIR /app

# Copy workspace manifests first (layer-cache friendly)
COPY pnpm-workspace.yaml package.json pnpm-lock.yaml* ./
COPY lib/api-zod/package.json          lib/api-zod/
COPY lib/api-client-react/package.json lib/api-client-react/
COPY lib/api-spec/package.json         lib/api-spec/
COPY lib/db/package.json               lib/db/
COPY artifacts/api-server/package.json artifacts/api-server/
COPY scripts/package.json              scripts/

# Install all dependencies (frozen)
RUN pnpm install --frozen-lockfile

# Copy source
COPY . .

# Build the API server bundle
RUN node artifacts/api-server/build.mjs

# ─── Stage 2: runtime ────────────────────────────────────────────────────────
FROM node:20-alpine AS runtime

RUN apk add --no-cache tini

WORKDIR /app

# Copy only the compiled bundle + runtime package.json
COPY --from=builder /app/artifacts/api-server/dist       ./dist
COPY --from=builder /app/artifacts/api-server/package.json ./package.json

# Install production-only deps (no dev tools)
RUN npm install --omit=dev --ignore-scripts 2>/dev/null || true

# Create non-root user
RUN addgroup -S botdeploy && adduser -S botdeploy -G botdeploy
USER botdeploy

# Runtime environment
ENV NODE_ENV=production \
    PORT=8097

EXPOSE 8097

# Use tini as PID 1 for proper signal handling
ENTRYPOINT ["/sbin/tini", "--"]
CMD ["node", "dist/index.mjs"]

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
  CMD wget -qO- http://localhost:8097/api/healthz | grep -q '"status":"ok"' || exit 1

# Stage 1: Base
FROM node:24-alpine AS base
WORKDIR /app
ENV PORT=3000
ENV HOSTNAME="0.0.0.0"
ENV NODE_OPTIONS="--dns-result-order=ipv4first"
ENV CYPRESS_INSTALL_BINARY=0

# Stage 2: Dependencies
FROM base AS deps
COPY package*.json ./
RUN npm ci --legacy-peer-deps || npm install

# Stage 3: Builder (Production Next.js Build)
FROM base AS builder
COPY --from=deps /app/node_modules ./node_modules
COPY . .
ENV NODE_ENV=production
ENV NEXT_TELEMETRY_DISABLED=1
ARG NEXT_PUBLIC_API_URL=http://localhost:8000/api
ARG NEXT_PUBLIC_APP_NAME="Swann OTT"
ENV NEXT_PUBLIC_API_URL=${NEXT_PUBLIC_API_URL}
ENV NEXT_PUBLIC_APP_NAME=${NEXT_PUBLIC_APP_NAME}
RUN npm run build

# Stage 4: Production Runner
FROM base AS runner
ENV NODE_ENV=production
ENV NEXT_TELEMETRY_DISABLED=1
WORKDIR /app

RUN addgroup --system --gid 1001 nodejs && \
    adduser --system --uid 1001 nextjs

RUN mkdir -p .next && chown nextjs:nodejs .next
COPY --from=builder --chown=nextjs:nodejs /app/public ./public
COPY --from=builder --chown=nextjs:nodejs /app/.next ./.next
COPY --from=builder --chown=nextjs:nodejs /app/node_modules ./node_modules
COPY --from=builder --chown=nextjs:nodejs /app/package*.json ./

USER nextjs
EXPOSE 3000

CMD ["npm", "run", "start"]

# Stage 5: Development (Used when target: dev is explicitly specified)
FROM base AS dev
ENV NODE_ENV=development
ENV WATCHPACK_POLLING=true
COPY package*.json ./
RUN npm install
COPY . .
EXPOSE 3000
CMD ["npm", "run", "dev"]

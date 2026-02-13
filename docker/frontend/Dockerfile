# --- Base commune ---
FROM node:22-alpine AS base
WORKDIR /app
RUN corepack enable

# --- Dépendances ---
FROM base AS deps
COPY ./frontend-parry-nuxt/package*.json ./
RUN npm install

# --- Build (prod) ---
FROM deps AS build
COPY ./frontend-parry-nuxt .
RUN npm run build

# --- Dev ---
FROM deps AS dev
WORKDIR /app
ENV HOST=0.0.0.0
ENV PORT=80
ENV CHOKIDAR_USEPOLLING=1
EXPOSE 80
CMD ["npx", "nuxt", "dev", "--host","0.0.0.0","--port","80"]

# --- Prod ---
FROM node:22-alpine AS prod
WORKDIR /app
ENV NODE_ENV=production
ENV PORT=80
COPY --from=build ./app/.output ./
EXPOSE 80
CMD ["node", "server/index.mjs"]
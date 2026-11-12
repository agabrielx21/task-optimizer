# Frontend image: build the Nuxt app, then run the standalone Nitro server (.output).

FROM node:24-alpine AS build
WORKDIR /app
# Dependencies first (cached until package*.json change). --ignore-scripts skips the
# "nuxt prepare" postinstall, which needs the sources; "nuxt build" does it later anyway.
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts
COPY . .
RUN npm run build

FROM node:24-alpine
WORKDIR /app
ENV NODE_ENV=production HOST=0.0.0.0 PORT=3000
# .output is self-contained: no node_modules needed at runtime.
COPY --from=build /app/.output ./.output
EXPOSE 3000
CMD ["node", ".output/server/index.mjs"]

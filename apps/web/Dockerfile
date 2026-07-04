# Vue 3 + TypeScript SPA — Vite dev server.
FROM node:22-alpine

WORKDIR /app

EXPOSE 5173

# On boot: install JS deps (if missing), then run Vite bound to all interfaces
# so the host can reach it.
CMD ["sh", "-c", "npm install && npm run dev -- --host 0.0.0.0 --port 5173"]

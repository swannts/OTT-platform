# Swann OTT Platform

An over-the-top (OTT) video streaming platform featuring a decoupled architecture:
1. **Customer website** (`apps/web/`) — Next.js streaming website with server rendering and HLS playback.
2. **API and platform backend** (`apps/api/`) — Laravel modular monolith, REST APIs, media, subscriptions, payments, and administrative APIs.
3. **Admin portal** (`apps/admin/`) — Next.js administrative portal with API BFF and role-based access controls.
4. **Mobile and TV client** (`apps/mobile/`) — Flutter applications for iOS, Android, and Smart TVs.
5. **Background worker** (`apps/worker/`) — Go worker for media and asynchronous jobs.

---

## Projects Structure

- [`apps/web/`](apps/web/README.md) — Next.js customer website.
- [`apps/api/`](apps/api/README.md) — Laravel backend API and modular monolith.
- [`apps/admin/`](apps/admin/README.md) — Next.js admin portal.
- [`apps/mobile/`](apps/mobile/README.md) — Flutter mobile and TV application.
- [`apps/worker/go.mod`](apps/worker/go.mod) — Go background worker module.

---

Next.js is the only end-user web frontend. Laravel's public Blade page routes are disabled; Laravel continues to provide its API and admin CMS, and its root URL has no customer-facing page or redirect. `FRONTEND_URL` is used by Laravel payment flows when returning customers to Next.js.

## Next.js End-User Web Routes

| Next.js Route | Description |
| :--- | :--- |
| `/` | Home page: hero banner, content carousels, and live TV |
| `/movies`, `/movies/[id]` | Movie catalog and details |
| `/tv-shows`, `/tv-shows/[id]` | TV series, seasons, and episodes |
| `/watch/[type]/[id]` | Adaptive video player and access checks |
| `/live-tv` | Live channel catalog and playback |
| `/pricing`, `/checkout/[planId]`, `/payment-success` | Subscription plans, checkout, and order status |
| `/search`, `/watchlist`, `/videos`, `/cast/[id]` | Discovery and saved content |
| `/account`, `/account/billing`, `/account/security`, `/profiles`, `/notifications` | Viewer account and profile management |
| `/login`, `/register` | Viewer authentication |
| `/pages/[slug]` | CMS pages and policies |

---

## Quick Start with Docker (Development Mode)

The root Compose file runs the local development stack: the admin portal, customer website, Laravel API, Go worker, MySQL with phpMyAdmin, Redis, RabbitMQ, and Meilisearch. Flutter is not included.

### 1. Launch All Services
```bash
docker compose up --build -d
```

### 2. Service Access & Endpoints
| Service | URL | Notes |
| :--- | :--- | :--- |
| **Customer website** | [http://localhost:3000](http://localhost:3000) | Fast Refresh / Turbopack |
| **Admin portal** | [http://localhost:3032](http://localhost:3032) | Fast Refresh |
| **Laravel API & CMS** | [http://localhost:8000](http://localhost:8000) | REST API v3 + Admin CMS |
| **MySQL Database** | `localhost:3306` | DB: `swann`, User: `root`, Password: `swann-local` |
| **phpMyAdmin** | [http://localhost:8081](http://localhost:8081) | Connects to the Compose MySQL service; sign in with the configured MySQL credentials |
| **Redis Cache** | `localhost:6379` | Cache & session storage |
| **RabbitMQ Management** | [http://localhost:15672](http://localhost:15672) | User: `swann`, Password: `swann-local` |
| **Meilisearch** | [http://localhost:7700](http://localhost:7700) | Full-text search engine |

### 3. Common Docker Commands
```bash
# View live logs
docker compose logs -f

# View frontend logs only
docker compose logs -f web

# View backend logs only
docker compose logs -f api

# Run database migrations inside the backend container
docker compose exec api php artisan migrate

# Safely seed local RBAC and the optional local administrator
docker compose exec api php artisan db:seed

# Stop all services
docker compose down
```

This repository uses the root `compose.yaml` for local development.

---

## Running Without Docker (Manual Mode)

### 1. Start Laravel Backend (Port 8000)
```bash
cd apps/api
php artisan serve --port=8000
```

### 2. Start Next.js Web App (Port 3000)
```bash
cd apps/web
npm run dev
```
Open [http://localhost:3000](http://localhost:3000) in your browser.

# Swann OTT Platform

An over-the-top (OTT) video streaming platform featuring a decoupled architecture:
1. **Next.js Web Client** (`nextjs-web/`) — High-performance, modern React streaming website with Server-Side Rendering (SSR/ISR), Turbopack, Tailwind CSS, and HLS playback.
2. **Laravel Headless Backend & Admin** (`swann-laravel-web/`) — REST API v3, media management, subscription engine, payment webhooks, and administrative CMS.
3. **Flutter Client** (`flutter-app/`) — Cross-platform client for iOS, Android, and Smart TVs.

---

## Projects Structure

- [`nextjs-web/`](nextjs-web/README.md) — Next.js 16+ frontend (App Router, Tailwind CSS, TypeScript, HLS.js, Sanctum auth).
- [`swann-laravel-web/`](swann-laravel-web/README.md) — Headless Laravel backend API, payment gateways, and admin CMS.
- [`flutter-app/`](flutter-app/README.md) — Flutter mobile & TV application.

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

The root Compose file runs the local development stack: the admin UI, customer Next.js frontend, Laravel API, Go worker, MySQL, Redis, RabbitMQ, and Meilisearch. Flutter is not included.

### 1. Launch All Services
```bash
docker compose up --build -d
```

### 2. Service Access & Endpoints
| Service | URL | Notes |
| :--- | :--- | :--- |
| **Next.js Web Client** | [http://localhost:3000](http://localhost:3000) | Fast Refresh / Turbopack |
| **Admin UI** | [http://localhost:3032](http://localhost:3032) | Fast Refresh |
| **Laravel API & CMS** | [http://localhost:8000](http://localhost:8000) | REST API v3 + Admin CMS |
| **MySQL Database** | `localhost:3306` | DB: `swann`, User: `root`, Password: `swann-local` |
| **Redis Cache** | `localhost:6379` | Cache & session storage |
| **RabbitMQ Management** | [http://localhost:15672](http://localhost:15672) | User: `swann`, Password: `swann-local` |
| **Meilisearch** | [http://localhost:7700](http://localhost:7700) | Full-text search engine |

### 3. Common Docker Commands
```bash
# View live logs
docker compose logs -f

# View frontend logs only
docker compose logs -f frontend

# View backend logs only
docker compose logs -f backend

# Run database migrations inside the backend container
docker compose exec backend php artisan migrate

# Seed the local development database
docker compose exec backend php artisan db:seed

# Stop all services
docker compose down
```

This repository uses only the root `docker-compose.yml` for local development.

---

## Running Without Docker (Manual Mode)

### 1. Start Laravel Backend (Port 8000)
```bash
cd swann-laravel-web
php artisan serve --port=8000
```

### 2. Start Next.js Web App (Port 3000)
```bash
cd nextjs-web
npm run dev
```
Open [http://localhost:3000](http://localhost:3000) in your browser.

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

## Next.js Frontend Page Coverage (100% Converted from Blade)

| Next.js Route | Equivalent Blade View | Description |
| :--- | :--- | :--- |
| `/` | `index.blade.php` | Home page: Hero banner, Top 10 carousel, Continue Watching, Genres |
| `/movies` | `movie.blade.php` | Movies catalog with category filtering |
| `/movies/[id]` | `movieDetail.blade.php` | Movie details, cast, reviews, related titles |
| `/tv-shows` | `tvShow.blade.php` | TV Shows catalog with category filtering |
| `/tv-shows/[id]` | `tvshowDetail.blade.php` | TV series details, seasons selector, episode browser |
| `/watch/[type]/[id]` | *Embedded Player* | Adaptive HLS.js video player, VIP paywall & rental access gate |
| `/live-tv` | `livetv.blade.php` | Live TV dashboard, categories, live stream launcher |
| `/pricing` | `subscriptionplan.blade.php` | Subscription tiers, duration toggle, feature comparison |
| `/checkout/[planId]` | `subscriptionPayment.blade.php` | Gateway checkout, coupon validation, price calculation |
| `/payment-success` | `paymentSuccess.blade.php` | Order receipt confirmation & status |
| `/search` | `search.blade.php` | Real-time multi-type search across movies, shows, and channels |
| `/watchlist` | `watchlist.blade.php` | Saved movies and series collection |
| `/videos` | `video.blade.php` | Short clips, backstage previews, and trailers |
| `/cast/[id]` | `castCrewDetail.blade.php` | Actor / Director biography and complete filmography credits |
| `/account` | `accountSetting.blade.php` | Profile settings, display name, contact info, password change |
| `/account/billing` | `paymentHistory.blade.php` | Active membership, subscription receipts, PPV rental history |
| `/account/security` | `securityControl.blade.php` | Parental control PIN restriction & remote device session logout |
| `/profiles` | `profileManagement.blade.php` | Multi-profile "Who's Watching?" switcher & Kids profile mode |
| `/notifications` | `notifications/list.blade.php` | In-app notification center with release alerts |
| `/login` | `auth/login.blade.php` | User sign-in with Sanctum authentication |
| `/register` | `auth/registration.blade.php` | User registration |
| `/pages/[slug]` | `privacyPolicy.blade.php`, `termsOfUse.blade.php`, `faq.blade.php` | Dynamic CMS pages & policy viewer |

---

## Quick Start with Docker (Development Mode)

The entire full stack (Frontend + Backend + MySQL + Redis + Meilisearch) is orchestrated with Docker Compose, featuring live source reload and hot refresh.

### 1. Launch All Services
```bash
docker compose up -d
```

### 2. Service Access & Endpoints
| Service | URL | Notes |
| :--- | :--- | :--- |
| **Next.js Web Client** | [http://localhost:3000](http://localhost:3000) | Fast Refresh / Turbopack |
| **Laravel API & CMS** | [http://localhost:8000](http://localhost:8000) | REST API v3 + Admin CMS |
| **MySQL Database** | `localhost:3306` | DB: `swann`, User: `root`, Password: none |
| **Redis Cache** | `localhost:6379` | Cache & session storage |
| **Meilisearch** | [http://localhost:7700](http://localhost:7700) | Full-text search engine |

### 3. Common Docker Commands
```bash
# View live logs
docker compose logs -f

# View frontend logs only
docker compose logs -f frontend

# View backend logs only
docker compose logs -f backend

# Run artisan command inside backend container
docker compose exec backend php artisan migrate

# Stop all services
docker compose down
```

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

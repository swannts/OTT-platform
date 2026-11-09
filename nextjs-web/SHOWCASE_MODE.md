# Frontend showcase mode

This branch runs the Next.js frontend without calling the Laravel API at runtime. The API client routes requests to `src/lib/showcase.ts`; its catalog, episode, video, banner, and live-channel snapshot comes from the local Docker MySQL database and is stored in `src/lib/showcase-backend-data.json`.

The snapshot was extracted on 2026-11-09 from the `swann` database tables `entertainments`, `videos`, `episodes`, `banners`, and `live_tv_channel`. Refresh it from Docker when the database changes. Artwork URLs point to the image URLs stored by the backend; the local SVG artwork under `public/showcase/posters` remains the fallback when an upstream image cannot load. Other showcase-only areas such as account actions, plans, and journal articles still use illustrative demo data.

`NEXT_PUBLIC_SHOWCASE_MODE` defaults to `true` when unset. Set it to `false` only when intentionally connecting this checkout to the real backend. Restart the development server after changing it; rebuild production output after changing it for a production build.

Login and registration accept demo values. Showcase changes such as watchlist items, reviews, and profile edits are saved in this browser's local storage. Checkout is simulated; no payment is processed.

Account actions and playback remain simulated. Playback uses a public sample HLS stream and is not a live broadcast. The catalog snapshot reflects the current local database and should be refreshed before relying on it as current production data.

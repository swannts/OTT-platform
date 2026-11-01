'use client';

import React, { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { MediaItem } from '@/types';
import HeroBanner from '@/components/home/HeroBanner';
import MediaRow from '@/components/media/MediaRow';

// High-quality fallback demo items in case backend is starting up
const fallbackBanners: MediaItem[] = [
  {
    id: 1,
    name: 'Cyber Horizon 2099',
    type: 'movie',
    description: 'In a neon-drenched metropolis, a rogue digital operative uncovers a conspiracy that threatens human consciousness itself.',
    banner_image: 'https://images.unsplash.com/photo-1578328819058-b69f3a3b0f6b?w=1600&auto=format&fit=crop&q=80',
    poster_image: 'https://images.unsplash.com/photo-1536440136628-849c177e76a1?w=500&auto=format&fit=crop&q=80',
    imdb_rating: 8.9,
    release_year: 2026,
    duration: '2h 15m',
    access: 'paid',
  },
  {
    id: 2,
    name: 'The Abyssal Realm',
    type: 'movie',
    description: 'A deep-sea scientific expedition discovers an ancient bioluminescent civilization beneath the oceanic crust.',
    banner_image: 'https://images.unsplash.com/photo-1518709268805-4e9042af9f23?w=1600&auto=format&fit=crop&q=80',
    poster_image: 'https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?w=500&auto=format&fit=crop&q=80',
    imdb_rating: 8.4,
    release_year: 2025,
    duration: '1h 58m',
    access: 'free',
  },
];

const fallbackTrending: MediaItem[] = [
  {
    id: 101,
    name: 'Neon Dynasty',
    type: 'movie',
    poster_image: 'https://images.unsplash.com/photo-1534447677768-be436bb09401?w=500&auto=format&fit=crop&q=80',
    imdb_rating: 8.7,
    release_year: 2026,
    duration: '2h 05m',
    access: 'paid',
  },
  {
    id: 102,
    name: 'Echoes of Valhalla',
    type: 'tvshow',
    poster_image: 'https://images.unsplash.com/photo-1518709268805-4e9042af9f23?w=500&auto=format&fit=crop&q=80',
    imdb_rating: 9.1,
    release_year: 2026,
    duration: 'Season 1',
    access: 'paid',
  },
  {
    id: 103,
    name: 'Solaris Outpost',
    type: 'movie',
    poster_image: 'https://images.unsplash.com/photo-1440404653325-ab127d49abc1?w=500&auto=format&fit=crop&q=80',
    imdb_rating: 7.9,
    release_year: 2025,
    duration: '1h 48m',
    access: 'free',
  },
  {
    id: 104,
    name: 'Midnight Protocol',
    type: 'movie',
    poster_image: 'https://images.unsplash.com/photo-1485846234645-a62644f84728?w=500&auto=format&fit=crop&q=80',
    imdb_rating: 8.2,
    release_year: 2025,
    duration: '2h 10m',
    access: 'paid',
  },
  {
    id: 105,
    name: 'The Quantum Paradox',
    type: 'movie',
    poster_image: 'https://images.unsplash.com/photo-1478760329108-5c3ed9d495a0?w=500&auto=format&fit=crop&q=80',
    imdb_rating: 8.5,
    release_year: 2026,
    duration: '2h 20m',
    access: 'pay-per-view',
  },
];

export default function HomePage() {
  const [banners, setBanners] = useState<MediaItem[]>([]);
  const [trending, setTrending] = useState<MediaItem[]>([]);
  const [top10, setTop10] = useState<MediaItem[]>([]);
  const [continueWatching, setContinueWatching] = useState<MediaItem[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    async function loadHomeData() {
      try {
        // Fetch Banners
        const bannerData = await api.getBannerData().catch(() => null);
        if (bannerData && Array.isArray(bannerData) && bannerData.length > 0) {
          setBanners(bannerData);
        } else {
          setBanners(fallbackBanners);
        }

        // Fetch Dashboard Data
        const dashboard = await api.getDashboard().catch(() => null);
        if (dashboard) {
          if (Array.isArray(dashboard.trending) && dashboard.trending.length > 0) {
            setTrending(dashboard.trending);
          } else {
            setTrending(fallbackTrending);
          }

          if (Array.isArray(dashboard.top_10) && dashboard.top_10.length > 0) {
            setTop10(dashboard.top_10);
          } else {
            setTop10(fallbackTrending.slice(0, 5));
          }
        } else {
          setTrending(fallbackTrending);
          setTop10(fallbackTrending.slice(0, 5));
        }

        // Fetch Continue Watching if logged in
        const cwData = await api.getContinueWatching().catch(() => null);
        if (cwData && Array.isArray(cwData)) {
          setContinueWatching(cwData);
        }
      } catch {
        setBanners(fallbackBanners);
        setTrending(fallbackTrending);
        setTop10(fallbackTrending.slice(0, 5));
      } finally {
        setIsLoading(false);
      }
    }

    loadHomeData();
  }, []);

  return (
    <div className="min-h-screen pb-16">
      {/* Hero Billboard Banner */}
      <HeroBanner items={banners.length > 0 ? banners : fallbackBanners} />

      {/* Rows Container with Negative Margin for Stacking Effect */}
      <div className="relative -mt-16 sm:-mt-24 z-20 space-y-2">
        {/* Continue Watching (for authenticated users) */}
        {continueWatching.length > 0 && (
          <MediaRow
            title="Continue Watching"
            items={continueWatching}
            aspectRatio="backdrop"
          />
        )}

        {/* Top 10 Today */}
        <MediaRow
          title="Top 10 Today"
          items={top10.length > 0 ? top10 : fallbackTrending.slice(0, 5)}
          showRank={true}
        />

        {/* Trending Now */}
        <MediaRow
          title="Trending Now"
          items={trending.length > 0 ? trending : fallbackTrending}
          moreHref="/movies"
        />

        {/* Popular TV Series */}
        <MediaRow
          title="Popular TV Shows & Series"
          items={fallbackTrending}
          moreHref="/tv-shows"
        />
      </div>
    </div>
  );
}

'use client';

import React, { useEffect, useState } from 'react';
import { api, normalizeMediaItem } from '@/lib/api';
import { MediaItem, LiveChannel } from '@/types';
import HeroBanner from '@/components/home/HeroBanner';
import MediaRow from '@/components/media/MediaRow';
import LiveNowSection from '@/components/home/LiveNowSection';

export default function HomePage() {
  const [banners, setBanners] = useState<MediaItem[]>([]);
  const [top10, setTop10] = useState<MediaItem[]>([]);
  const [latestMovies, setLatestMovies] = useState<MediaItem[]>([]);
  const [popularMovies, setPopularMovies] = useState<MediaItem[]>([]);
  const [payPerView, setPayPerView] = useState<MediaItem[]>([]);
  const [tvShows, setTvShows] = useState<MediaItem[]>([]);
  const [liveChannels, setLiveChannels] = useState<LiveChannel[]>([]);
  const [continueWatching, setContinueWatching] = useState<MediaItem[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    async function loadBackendData() {
      setIsLoading(true);
      try {
        // 1. Fetch real banners from backend
        const bannerData = await api.getBannerData('home').catch(() => []);
        if (Array.isArray(bannerData) && bannerData.length > 0) {
          setBanners(bannerData);
        }

        // 2. Fetch main dashboard data from backend
        const dashboard = await api.getDashboard().catch(() => null);
        if (dashboard) {
          if (dashboard.top_10?.data && Array.isArray(dashboard.top_10.data)) {
            setTop10(dashboard.top_10.data.map(normalizeMediaItem));
          }
          if (dashboard.latest_movie?.data && Array.isArray(dashboard.latest_movie.data)) {
            setLatestMovies(dashboard.latest_movie.data.map(normalizeMediaItem));
          }
          if (dashboard.popular_movie?.data && Array.isArray(dashboard.popular_movie.data)) {
            setPopularMovies(dashboard.popular_movie.data.map(normalizeMediaItem));
          }
          if (dashboard.pay_per_view?.data && Array.isArray(dashboard.pay_per_view.data)) {
            setPayPerView(dashboard.pay_per_view.data.map(normalizeMediaItem));
          }
        }

        // 3. Fetch real live TV channels from backend
        const liveTvData = await api.getLiveTvDashboard().catch(() => null);
        if (liveTvData) {
          const channels = liveTvData.slider || [];
          if (Array.isArray(channels) && channels.length > 0) {
            setLiveChannels(channels);
          } else if (Array.isArray(liveTvData.category_data) && liveTvData.category_data.length > 0) {
            // Flatten first category's channels
            const flat = liveTvData.category_data.flatMap((c: any) => c.channel_data || []);
            setLiveChannels(flat);
          }
        }

        // 4. Fetch real TV Series from backend
        const tvData = await api.getTvShows({ page: 1 }).catch(() => null);
        const tvList = Array.isArray(tvData) ? tvData : tvData?.data || [];
        if (Array.isArray(tvList) && tvList.length > 0) {
          setTvShows(tvList.map(normalizeMediaItem));
        }

        // 5. Fetch user Continue Watching if authenticated
        const cwData = await api.getContinueWatching().catch(() => null);
        if (cwData && Array.isArray(cwData) && cwData.length > 0) {
          setContinueWatching(cwData.map(normalizeMediaItem));
        }
      } catch (err) {
        console.error('Error loading data from backend', err);
      } finally {
        setIsLoading(false);
      }
    }

    loadBackendData();
  }, []);

  return (
    <div className="min-h-screen pb-16">
      {/* 1. Hero Billboard Banner (Direct from Backend Banners) */}
      <HeroBanner items={banners} />

      {/* 2. Content Rows Container */}
      <div className="relative -mt-10 sm:-mt-16 z-20 space-y-4">
        {/* Continue Watching (from backend watch history) */}
        {continueWatching.length > 0 && (
          <MediaRow
            title="Continue Watching"
            items={continueWatching}
            aspectRatio="backdrop"
          />
        )}

        {/* Trending / Latest Movies (from backend latest_movie) */}
        {latestMovies.length > 0 && (
          <MediaRow
            title="Trending Now"
            items={latestMovies}
            moreHref="/movies"
            aspectRatio="backdrop"
          />
        )}

        {/* Live Now (from backend Live TV channels) */}
        <LiveNowSection channels={liveChannels} />

        {/* Top 10 Today (from backend top_10 with rank badges) */}
        {top10.length > 0 && (
          <MediaRow
            title="Top 10 Today"
            items={top10}
            showRank={true}
            aspectRatio="poster"
            moreHref="/movies"
          />
        )}

        {/* Featured TV Shows (from backend tvshow-list) */}
        {tvShows.length > 0 && (
          <MediaRow
            title="Popular TV Shows & Series"
            items={tvShows}
            moreHref="/tv-shows"
            aspectRatio="backdrop"
          />
        )}

        {/* Popular Movies (from backend popular_movie) */}
        {popularMovies.length > 0 && (
          <MediaRow
            title="Popular Movies"
            items={popularMovies}
            moreHref="/movies"
            aspectRatio="backdrop"
          />
        )}

        {/* Pay-Per-View Rentals (from backend pay_per_view) */}
        {payPerView.length > 0 && (
          <MediaRow
            title="Pay-Per-View Exclusives"
            items={payPerView}
            moreHref="/movies"
            aspectRatio="backdrop"
          />
        )}
      </div>
    </div>
  );
}

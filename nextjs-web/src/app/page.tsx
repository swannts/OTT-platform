'use client';

import React, { useEffect } from 'react';
import { useAppDispatch, useAppSelector } from '@/store/hooks';
import { loadAllHomeContent } from '@/store/slices/homeSlice';
import HeroBanner from '@/components/home/HeroBanner';
import MediaRow from '@/components/media/MediaRow';
import LiveNowSection from '@/components/home/LiveNowSection';

export default function HomePage() {
  const dispatch = useAppDispatch();
  const {
    banners,
    top10,
    latestMovies,
    popularMovies,
    payPerView,
    tvShows,
    liveChannels,
    continueWatching,
  } = useAppSelector((state) => state.home);

  useEffect(() => {
    dispatch(loadAllHomeContent());
  }, [dispatch]);

  return (
    <div className="min-h-screen pb-16">
      {/* 1. Hero Billboard Banner (Direct from Backend Banners) */}
      <HeroBanner items={banners} />

      {/* 2. Content Container After Slider with generous container gap */}
      <div className="max-w-[1360px] mx-auto px-8 sm:px-12 md:px-16 lg:px-20 xl:px-24 relative z-20 pt-10 sm:pt-14 space-y-14 sm:space-y-16">
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

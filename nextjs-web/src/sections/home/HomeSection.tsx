'use client';

import React, { useEffect } from 'react';
import { useAppDispatch, useAppSelector } from '@/store/hooks';
import { setInitialHomeData, fetchContinueWatching, fetchDashboardData } from '@/store/slices/homeSlice';
import HeroBanner from './HeroBanner';
import MediaRow from '@/components/media/MediaRow';
import LiveNowSection from './LiveNowSection';
import { MediaItem, LiveChannel } from '@/types';
import { useAuth } from '@/context/AuthContext';

interface HomeSectionProps {
  initialBanners: MediaItem[];
  initialTop10: MediaItem[];
  initialLatestMovies: MediaItem[];
  initialPopularMovies: MediaItem[];
  initialPayPerView: MediaItem[];
  initialTvShows: MediaItem[];
  initialLiveChannels: LiveChannel[];
}

export default function HomeSection({
  initialBanners,
  initialTop10,
  initialLatestMovies,
  initialPopularMovies,
  initialPayPerView,
  initialTvShows,
  initialLiveChannels,
}: HomeSectionProps) {
  const dispatch = useAppDispatch();
  const { user } = useAuth();
  const state = useAppSelector((s) => s.home);

  useEffect(() => {
    dispatch(
      setInitialHomeData({
        banners: initialBanners,
        top10: initialTop10,
        latestMovies: initialLatestMovies,
        popularMovies: initialPopularMovies,
        payPerView: initialPayPerView,
        tvShows: initialTvShows,
        liveChannels: initialLiveChannels,
      })
    );
    if (!initialLatestMovies || initialLatestMovies.length === 0) {
      dispatch(fetchDashboardData());
    }
    if (user) {
      dispatch(fetchContinueWatching());
    }
  }, [
    dispatch,
    user,
    initialBanners,
    initialTop10,
    initialLatestMovies,
    initialPopularMovies,
    initialPayPerView,
    initialTvShows,
    initialLiveChannels,
  ]);

  // Use Redux state if populated, falling back seamlessly to server-rendered initial data
  const banners = state.banners.length > 0 ? state.banners : initialBanners;
  const top10 = state.top10.length > 0 ? state.top10 : initialTop10;
  const latestMovies = state.latestMovies.length > 0 ? state.latestMovies : initialLatestMovies;
  const popularMovies = state.popularMovies.length > 0 ? state.popularMovies : initialPopularMovies;
  const payPerView = state.payPerView.length > 0 ? state.payPerView : initialPayPerView;
  const tvShows = state.tvShows.length > 0 ? state.tvShows : initialTvShows;
  const liveChannels = state.liveChannels.length > 0 ? state.liveChannels : initialLiveChannels;
  const continueWatching = state.continueWatching;

  return (
    <div className="min-h-screen pb-16">
      {/* 1. Hero Billboard Banner (SSR rendered) */}
      <HeroBanner items={banners} />

      {/* 2. Content Container After Slider */}
      <div className="max-w-[1360px] mx-auto px-8 sm:px-12 md:px-16 lg:px-20 xl:px-24 relative z-20 pt-10 sm:pt-14 space-y-14 sm:space-y-16">
        {/* Continue Watching (User specific) */}
        {continueWatching.length > 0 && (
          <MediaRow
            title="Continue Watching"
            items={continueWatching}
            aspectRatio="backdrop"
          />
        )}

        {/* Trending / Latest Movies */}
        {latestMovies.length > 0 && (
          <MediaRow
            title="Trending Now"
            items={latestMovies}
            moreHref="/movies"
            aspectRatio="backdrop"
          />
        )}

        {/* Live Now (Makkah & Madinah Live streams) */}
        <LiveNowSection channels={liveChannels} />

        {/* Top 10 Today with Rank badges */}
        {top10.length > 0 && (
          <MediaRow
            title="Top 10 Today"
            items={top10}
            showRank={true}
            aspectRatio="poster"
            moreHref="/movies"
          />
        )}

        {/* Featured TV Shows & Series */}
        {tvShows.length > 0 && (
          <MediaRow
            title="Popular TV Shows & Series"
            items={tvShows}
            moreHref="/tv-shows"
            aspectRatio="backdrop"
          />
        )}

        {/* Popular Islamic Movies */}
        {popularMovies.length > 0 && (
          <MediaRow
            title="Popular Movies"
            items={popularMovies}
            moreHref="/movies"
            aspectRatio="backdrop"
          />
        )}

        {/* Pay-Per-View Rentals */}
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


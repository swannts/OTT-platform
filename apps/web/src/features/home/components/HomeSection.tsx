'use client';

import React, { useEffect } from 'react';
import { useAppDispatch, useAppSelector } from '@/store/hooks';
import { setInitialHomeData, fetchContinueWatching, fetchDashboardData, selectHomeState } from '@/store/slices/homeSlice';
import HeroBanner from './HeroBanner';
import { MediaRow } from '@/components/shared';
import LiveNowSection from './LiveNowSection';
import { MediaItem, LiveChannel } from '@/types';
import { useAuth } from '@/context/AuthContext';
import { useLanguage } from '@/providers';
import { BookOpen } from 'lucide-react';

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
  const { t } = useLanguage();
  const state = useAppSelector(selectHomeState);

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
  const hasCatalogContent =
    banners.length > 0 ||
    top10.length > 0 ||
    latestMovies.length > 0 ||
    popularMovies.length > 0 ||
    payPerView.length > 0 ||
    tvShows.length > 0 ||
    liveChannels.length > 0 ||
    continueWatching.length > 0;

  return (
    <div className="min-h-screen pb-16">
      {hasCatalogContent ? (
        <>
          {/* 1. Hero Billboard Banner (SSR rendered) */}
          <HeroBanner items={banners} />

          {/* 2. Content Container After Slider */}
          <div className="max-w-[1360px] mx-auto px-8 sm:px-12 md:px-16 lg:px-20 xl:px-24 relative z-20 pt-10 sm:pt-14 space-y-14 sm:space-y-16">
            {/* Continue Watching (User specific) */}
            {continueWatching.length > 0 && (
              <MediaRow
                title={t('continue_watching', 'Continue Watching')}
                items={continueWatching}
                aspectRatio="backdrop"
              />
            )}

            {/* Trending / Latest Movies */}
            {latestMovies.length > 0 && (
              <MediaRow
                title={t('trending_now', 'Trending Now')}
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
                title={t('top_10_today', 'Top 10 Today')}
                items={top10}
                showRank={true}
                aspectRatio="poster"
                moreHref="/movies"
              />
            )}

            {/* Featured TV Shows & Series */}
            {tvShows.length > 0 && (
              <MediaRow
                title={t('popular_tv_shows', 'Popular TV Shows & Series')}
                items={tvShows}
                moreHref="/tv-shows"
                aspectRatio="backdrop"
              />
            )}

            {/* Popular Islamic Movies */}
            {popularMovies.length > 0 && (
              <MediaRow
                title={t('popular_movies', 'Popular Movies')}
                items={popularMovies}
                moreHref="/movies"
                aspectRatio="backdrop"
              />
            )}

            {/* Pay-Per-View Rentals */}
            {payPerView.length > 0 && (
              <MediaRow
                title={t('pay_per_view_exclusives', 'Pay-Per-View Exclusives')}
                items={payPerView}
                moreHref="/movies"
                aspectRatio="backdrop"
              />
            )}
          </div>
        </>
      ) : (
        <section className="relative flex min-h-[70vh] items-center justify-center overflow-hidden px-6 text-center">
          <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_center,rgba(229,184,105,0.12),transparent_55%)]" />
          <div className="relative max-w-xl">
            <div className="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-2xl border border-[#E5B869]/25 bg-[#E5B869]/10 text-[#E5B869]">
              <BookOpen aria-hidden="true" className="h-7 w-7" />
            </div>
            <h1 className="font-serif-title text-3xl font-bold text-white sm:text-4xl">
              {t('home_catalog_empty_title')}
            </h1>
            <p className="mt-4 text-sm leading-7 text-gray-400 sm:text-base">
              {t('home_catalog_empty_description')}
            </p>
          </div>
        </section>
      )}
    </div>
  );
}

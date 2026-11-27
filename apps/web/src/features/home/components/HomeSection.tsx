'use client';

import { useEffect } from 'react';
import { useAppDispatch, useAppSelector } from '@/store/hooks';
import {
  fetchContinueWatching,
  fetchHomePageContent,
  hydrateHomeContent,
  selectHomeState,
  type HomeContent,
} from '@/store/slices/homeSlice';
import { useAuth } from '@/context/AuthContext';
import HeroBanner from './HeroBanner';
import { MediaRow } from '@/components/shared';
import LiveNowSection from './LiveNowSection';
import { useLanguage } from '@/providers';
import { BookOpen } from 'lucide-react';

interface HomeSectionProps {
  initialContent: HomeContent;
}

export default function HomeSection({ initialContent }: HomeSectionProps) {
  const dispatch = useAppDispatch();
  const home = useAppSelector(selectHomeState);
  const { user } = useAuth();
  const { t } = useLanguage();
  const content = home.hasLoaded ? home : initialContent;

  useEffect(() => {
    dispatch(hydrateHomeContent(initialContent));
    if (!Object.values(initialContent).some((section) => section.length > 0)) {
      void dispatch(fetchHomePageContent());
    }
  }, [dispatch, initialContent]);

  useEffect(() => {
    if (user) void dispatch(fetchContinueWatching());
  }, [dispatch, user]);

  const {
    banners,
    top10,
    latestMovies,
    popularMovies,
    payPerView,
    tvShows,
    liveChannels,
  } = content;
  const { continueWatching, isLoading, error } = home;

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
          {/* Hero billboard */}
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
              {isLoading ? (
                <span className="h-7 w-7 animate-spin rounded-full border-2 border-[#E5B869]/30 border-t-[#E5B869]" aria-hidden="true" />
              ) : (
                <BookOpen aria-hidden="true" className="h-7 w-7" />
              )}
            </div>
            <h1 className="font-serif-title text-3xl font-bold text-white sm:text-4xl">
              {isLoading ? t('loading', 'Loading your library…') : error ? t('home_catalog_error_title', 'We could not load the library') : t('home_catalog_empty_title')}
            </h1>
            <p className="mt-4 text-sm leading-7 text-gray-400 sm:text-base">
              {isLoading
                ? t('home_catalog_loading_description', 'Fetching the latest movies, series, and live channels.')
                : error
                  ? error
                  : t('home_catalog_empty_description')}
            </p>
            {!isLoading && (
              <button
                type="button"
                onClick={() => void dispatch(fetchHomePageContent())}
                className="mt-6 rounded-full bg-[#E5B869] px-5 py-2.5 text-sm font-semibold text-[#111] transition hover:bg-[#f0c779] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#E5B869]"
              >
                {t('retry', 'Try again')}
              </button>
            )}
          </div>
        </section>
      )}
    </div>
  );
}

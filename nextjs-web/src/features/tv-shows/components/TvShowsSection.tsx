'use client';

import React, { useEffect, useState, useRef, useCallback } from 'react';
import { useAppDispatch, useAppSelector } from '@/store/hooks';
import {
  setInitialCursorData,
  fetchNextTvShowsCursor,
  selectTvShowsState,
} from '@/store/slices/tvShowsSlice';
import { MediaGrid } from '@/components/shared';
import { Tv, Sparkles, Filter, Loader2, CheckCircle2 } from 'lucide-react';
import { MediaItem } from '@/types';
import { SectionHeader, EmptyState } from '@/components/ui';

interface TvShowsSectionProps {
  initialShows: MediaItem[];
  initialCursor?: string | null;
  initialHasMore?: boolean;
}

export default function TvShowsSection({
  initialShows,
  initialCursor = null,
  initialHasMore = false,
}: TvShowsSectionProps) {
  const dispatch = useAppDispatch();
  const {
    shows: reduxShows,
    cursor,
    hasMore,
    isLoading,
    isLoadingMore,
  } = useAppSelector(selectTvShowsState);
  const [selectedCategory, setSelectedCategory] = useState<string>('all');
  const sentinelRef = useRef<HTMLDivElement | null>(null);

  // Sync initial SSR data into Redux on mount
  useEffect(() => {
    if (initialShows.length > 0) {
      dispatch(
        setInitialCursorData({
          shows: initialShows,
          nextCursor: initialCursor,
          hasMore: initialHasMore,
        })
      );
    }
  }, [dispatch, initialShows, initialCursor, initialHasMore]);

  const shows = reduxShows.length > 0 ? reduxShows : initialShows;
  const currentCursor = cursor ?? initialCursor;
  const isMoreAvailable = hasMore ?? initialHasMore;
  const canLoadMore = isMoreAvailable && !isLoadingMore && !isLoading;

  const handleLoadMore = useCallback(() => {
    if (!canLoadMore) return;
    dispatch(fetchNextTvShowsCursor({ cursor: currentCursor, per_page: 12 }));
  }, [canLoadMore, dispatch, currentCursor]);

  // Infinite Scroll IntersectionObserver with 350px anticipation margin
  useEffect(() => {
    const sentinel = sentinelRef.current;
    if (!sentinel || !canLoadMore) return;

    const observer = new IntersectionObserver(
      (entries) => {
        const first = entries[0];
        if (first?.isIntersecting) {
          handleLoadMore();
        }
      },
      {
        root: null,
        rootMargin: '350px',
        threshold: 0.1,
      }
    );

    observer.observe(sentinel);
    return () => {
      observer.disconnect();
    };
  }, [canLoadMore, handleLoadMore]);

  const availableGenres = Array.from(new Set(shows.flatMap((show) => show.genre_data || []))).sort((a, b) => a.localeCompare(b));
  const filteredShows = selectedCategory === 'all'
    ? shows
    : shows.filter((show) => show.genre_data?.some((genre) => genre.toLocaleLowerCase() === selectedCategory.toLocaleLowerCase()));
  const categories = [
    { id: 'all', label: 'All Series' },
    ...availableGenres.map((genre) => ({ id: genre, label: genre })),
  ];

  return (
    <div className="min-h-screen pt-24 sm:pt-28 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto pb-24 select-none">
      {/* Page Header */}
      <div className="mb-8 border-b border-white/10 pb-6">
        <SectionHeader
          className="mb-0 border-b-0 pb-0"
          badgeText="TV SERIES"
          badgeIcon={<Sparkles className="w-3.5 h-3.5 text-[#E5B869]" />}
          title="Islamic TV Shows & Series"
          titleIcon={<Tv className="w-7 h-7 sm:w-8 sm:h-8 text-[#E5B869]" />}
          description="Browse shows and episodes currently available in the catalog."
          counterText={`${filteredShows.length} Series Shown`}
        />

        {/* Category Filter Pills */}
        <div className="flex items-center space-x-2 overflow-x-auto no-scrollbar pt-6 pb-1">
          <Filter className="w-4 h-4 text-gray-400 flex-shrink-0 mr-1" />
          {categories.map((cat) => {
            const isSelected = selectedCategory === cat.id;
            return (
              <button
                key={cat.id}
                type="button"
                onClick={() => setSelectedCategory(cat.id)}
                className={`px-3.5 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap transition-all cursor-pointer outline-none focus:outline-none ${
                  isSelected
                    ? 'bg-[#E5B869] text-black shadow-md shadow-[#E5B869]/20 font-bold'
                    : 'bg-white/5 hover:bg-white/10 text-gray-300 border border-white/10 hover:border-white/20'
                }`}
              >
                {cat.label}
              </button>
            );
          })}
        </div>
      </div>

      {/* Grid of Shows */}
      <MediaGrid
        items={filteredShows}
        isLoading={isLoading}
        aspectRatio="backdrop"
        emptyState={
          <EmptyState
            icon={<Tv className="w-10 h-10 text-gray-500" />}
            title="No series found in this category"
            description="Try selecting another category filter above."
          />
        }
      />

      {/* 4. Infinite Scroll Sentinel & Loading Feedback */}
      <div className="mt-12 flex flex-col items-center justify-center min-h-[60px]">
        {/* Invisible Observer Target */}
        <div ref={sentinelRef} className="h-6 w-full pointer-events-none" />

        {/* Loading Spinner */}
        {isLoadingMore && (
          <div className="flex items-center space-x-3 text-[#E5B869] bg-white/[0.04] border border-[#E5B869]/30 px-6 py-3 rounded-full backdrop-blur-md shadow-lg shadow-[#E5B869]/10 animate-pulse">
            <Loader2 className="w-4 h-4 animate-spin text-[#E5B869]" />
            <span className="text-xs font-bold tracking-wide text-white">
              Loading more Islamic series...
            </span>
          </div>
        )}

        {/* End of Collection Notice */}
        {!isMoreAvailable && shows.length > 0 && (
          <div className="flex items-center space-x-2 text-xs text-gray-400 bg-white/[0.02] border border-white/5 px-5 py-2.5 rounded-full">
            <CheckCircle2 className="w-3.5 h-3.5 text-[#E5B869]" />
            <span>You have reached the end of the collection • {shows.length} Series</span>
          </div>
        )}
      </div>
    </div>
  );
}

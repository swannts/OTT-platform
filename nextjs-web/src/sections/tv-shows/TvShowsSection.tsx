'use client';

import React, { useEffect, useState } from 'react';
import { useAppDispatch, useAppSelector } from '@/store/hooks';
import { setInitialShows, fetchTvShowsList, selectTvShowsState } from '@/store/slices/tvShowsSlice';
import MediaGrid from '@/components/media/MediaGrid';
import { Tv, Sparkles, Filter } from 'lucide-react';
import { MediaItem } from '@/types';
import { SectionHeader, EmptyState } from '@/components/ui';
import { TV_SERIES_CATEGORIES } from '@/constants';

interface TvShowsSectionProps {
  initialShows: MediaItem[];
}

export default function TvShowsSection({ initialShows }: TvShowsSectionProps) {
  const dispatch = useAppDispatch();
  const { shows: reduxShows, isLoading, page } = useAppSelector(selectTvShowsState);
  const [selectedCategory, setSelectedCategory] = useState<string>('all');

  useEffect(() => {
    if (initialShows.length > 0) {
      dispatch(setInitialShows(initialShows));
    }
  }, [dispatch, initialShows]);

  const shows = reduxShows.length > 0 ? reduxShows : initialShows;

  const filteredShows = shows.filter((show) => {
    if (selectedCategory === 'all') return true;
    const cat = TV_SERIES_CATEGORIES.find((c) => c.id === selectedCategory);
    if (!cat?.keywords) return true;
    const text = `${show.name || ''} ${show.description || ''}`.toLowerCase();
    return cat.keywords.some((kw) => text.includes(kw));
  });

  return (
    <div className="min-h-screen pt-24 sm:pt-28 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto pb-20 select-none">
      {/* Page Header */}
      <div className="mb-8 border-b border-white/10 pb-6">
        <SectionHeader
          className="mb-0 border-b-0 pb-0"
          badgeText="Sacred Series & Docuseries"
          badgeIcon={<Sparkles className="w-3.5 h-3.5 text-[#E5B869]" />}
          title="Islamic TV Shows & Series"
          titleIcon={<Tv className="w-7 h-7 sm:w-8 sm:h-8" />}
          description="Inspirational Prophetic chronicles, historical educational docuseries, and faith-based family series."
          counterText={`${filteredShows.length} Series Available`}
        />

        {/* Category Filter Pills */}
        <div className="flex items-center space-x-2 overflow-x-auto no-scrollbar pt-6 pb-1">
          <Filter className="w-4 h-4 text-gray-400 flex-shrink-0 mr-1" />
          {TV_SERIES_CATEGORIES.map((cat) => {
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
    </div>
  );
}


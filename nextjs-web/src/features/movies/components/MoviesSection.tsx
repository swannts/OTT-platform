'use client';

import React, { useEffect, useState } from 'react';
import { useAppDispatch, useAppSelector } from '@/store/hooks';
import { setInitialMovies, fetchMoviesList, selectMoviesState } from '@/store/slices/moviesSlice';
import { MediaGrid } from '@/components/shared';
import { Film, Sparkles, Filter } from 'lucide-react';
import { MediaItem } from '@/types';
import { SectionHeader, EmptyState } from '@/components/ui';
import { MOVIE_CATEGORIES } from '@/constants';

interface MoviesSectionProps {
  initialMovies: MediaItem[];
}

export default function MoviesSection({ initialMovies }: MoviesSectionProps) {
  const dispatch = useAppDispatch();
  const { movies: reduxMovies, isLoading, page } = useAppSelector(selectMoviesState);
  const [selectedCategory, setSelectedCategory] = useState<string>('all');

  useEffect(() => {
    if (initialMovies.length > 0) {
      dispatch(setInitialMovies(initialMovies));
    }
  }, [dispatch, initialMovies]);

  const movies = reduxMovies.length > 0 ? reduxMovies : initialMovies;

  const filteredMovies = movies.filter((movie) => {
    if (selectedCategory === 'all') return true;
    const cat = MOVIE_CATEGORIES.find((c) => c.id === selectedCategory);
    if (!cat?.keywords) return true;
    const text = `${movie.name || ''} ${movie.description || ''}`.toLowerCase();
    return cat.keywords.some((kw) => text.includes(kw));
  });

  return (
    <div className="min-h-screen pt-24 sm:pt-28 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto pb-20 select-none">
      {/* Page Header */}
      <div className="mb-8 border-b border-white/10 pb-6">
        <SectionHeader
          className="mb-0 border-b-0 pb-0"
          badgeText="Cinematic Features & Documentaries"
          badgeIcon={<Sparkles className="w-3.5 h-3.5 text-[#E5B869]" />}
          title="Islamic Movies & Documentaries"
          titleIcon={<Film className="w-7 h-7 sm:w-8 sm:h-8" />}
          description="Browse our curated collection of Islamic documentaries, faith-inspired cinema, and sacred history."
          counterText={`${filteredMovies.length} Titles Available`}
        />

        {/* Category Filter Pills */}
        <div className="flex items-center space-x-2 overflow-x-auto no-scrollbar pt-6 pb-1">
          <Filter className="w-4 h-4 text-gray-400 flex-shrink-0 mr-1" />
          {MOVIE_CATEGORIES.map((cat) => {
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

      {/* Grid of Movies */}
      <MediaGrid
        items={filteredMovies}
        isLoading={isLoading}
        aspectRatio="backdrop"
        emptyState={
          <EmptyState
            icon={<Film className="w-10 h-10 text-gray-500" />}
            title="No titles found in this category"
            description="Try selecting another category filter above."
          />
        }
      />
    </div>
  );
}


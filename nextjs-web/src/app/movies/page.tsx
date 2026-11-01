'use client';

import React, { useEffect } from 'react';
import { useAppDispatch, useAppSelector } from '@/store/hooks';
import { fetchMoviesList } from '@/store/slices/moviesSlice';
import MediaCard from '@/components/media/MediaCard';
import { Film } from 'lucide-react';

export default function MoviesPage() {
  const dispatch = useAppDispatch();
  const { movies, isLoading, page } = useAppSelector((state) => state.movies);

  useEffect(() => {
    dispatch(fetchMoviesList({ page }));
  }, [dispatch, page]);

  return (
    <div className="min-h-screen pt-24 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto pb-16">
      <div className="flex items-center justify-between mb-8 border-b border-white/10 pb-4">
        <div>
          <h1 className="text-2xl sm:text-3xl font-extrabold text-white flex items-center space-x-2">
            <Film className="w-7 h-7 text-[#E5B869]" />
            <span>Islamic Movies & Documentaries</span>
          </h1>
          <p className="text-sm text-gray-400 mt-1">
            Browse our curated collection of Islamic documentaries, faith-inspired cinema, and sacred history.
          </p>
        </div>
      </div>

      {isLoading ? (
        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
          {[...Array(10)].map((_, i) => (
            <div key={i} className="aspect-[2/3] bg-white/5 rounded-xl animate-pulse" />
          ))}
        </div>
      ) : (
        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4 sm:gap-6">
          {movies.map((movie) => (
            <MediaCard key={movie.id} item={movie} />
          ))}
        </div>
      )}
    </div>
  );
}


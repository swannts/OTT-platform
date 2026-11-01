'use client';

import React, { useEffect, useState } from 'react';
import { api, normalizeMediaItem } from '@/lib/api';
import { MediaItem } from '@/types';
import MediaCard from '@/components/media/MediaCard';
import { Film, Filter } from 'lucide-react';

export default function MoviesPage() {
  const [movies, setMovies] = useState<MediaItem[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [page, setPage] = useState(1);

  useEffect(() => {
    async function fetchMovies() {
      setIsLoading(true);
      try {
        const res = await api.getMovies({ page });
        const list = Array.isArray(res) ? res : res?.data || [];
        if (Array.isArray(list) && list.length > 0) {
          setMovies(list.map(normalizeMediaItem));
        }
      } catch (err) {
        console.error('Failed to load movies from backend', err);
      } finally {
        setIsLoading(false);
      }
    }

    fetchMovies();
  }, [page]);

  return (
    <div className="min-h-screen pt-24 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto pb-16">
      <div className="flex items-center justify-between mb-8 border-b border-white/10 pb-4">
        <div>
          <h1 className="text-2xl sm:text-3xl font-extrabold text-white flex items-center space-x-2">
            <Film className="w-7 h-7 text-red-600" />
            <span>Explore Movies</span>
          </h1>
          <p className="text-sm text-gray-400 mt-1">
            Browse our complete library of Hollywood blockbusters, indie cinema, and 4K releases.
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


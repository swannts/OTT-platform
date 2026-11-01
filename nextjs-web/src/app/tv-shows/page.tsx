'use client';

import React, { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { MediaItem } from '@/types';
import MediaCard from '@/components/media/MediaCard';
import { Tv } from 'lucide-react';

export default function TvShowsPage() {
  const [shows, setShows] = useState<MediaItem[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    async function fetchTvShows() {
      setIsLoading(true);
      try {
        const res = await api.getTvShows();
        if (res && Array.isArray(res)) {
          setShows(res);
        } else if (res?.data && Array.isArray(res.data)) {
          setShows(res.data);
        }
      } catch {
        setShows([
          {
            id: 301,
            name: 'Echoes of Valhalla',
            type: 'tvshow',
            poster_image: 'https://images.unsplash.com/photo-1518709268805-4e9042af9f23?w=500&auto=format&fit=crop&q=80',
            imdb_rating: 9.1,
            release_year: 2026,
            duration: '3 Seasons',
            access: 'paid',
          },
          {
            id: 302,
            name: 'Silicon Syndicate',
            type: 'tvshow',
            poster_image: 'https://images.unsplash.com/photo-1485846234645-a62644f84728?w=500&auto=format&fit=crop&q=80',
            imdb_rating: 8.7,
            release_year: 2025,
            duration: '2 Seasons',
            access: 'free',
          },
          {
            id: 303,
            name: 'The Andromeda Rift',
            type: 'tvshow',
            poster_image: 'https://images.unsplash.com/photo-1478760329108-5c3ed9d495a0?w=500&auto=format&fit=crop&q=80',
            imdb_rating: 8.6,
            release_year: 2026,
            duration: '1 Season',
            access: 'paid',
          },
        ]);
      } finally {
        setIsLoading(false);
      }
    }

    fetchTvShows();
  }, []);

  return (
    <div className="min-h-screen pt-24 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto pb-16">
      <div className="flex items-center justify-between mb-8 border-b border-white/10 pb-4">
        <div>
          <h1 className="text-2xl sm:text-3xl font-extrabold text-white flex items-center space-x-2">
            <Tv className="w-7 h-7 text-red-600" />
            <span>TV Shows & Series</span>
          </h1>
          <p className="text-sm text-gray-400 mt-1">
            Binge-worthy original series, multi-season epics, and weekly episodic drops.
          </p>
        </div>
      </div>

      {isLoading ? (
        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
          {[...Array(8)].map((_, i) => (
            <div key={i} className="aspect-[2/3] bg-white/5 rounded-xl animate-pulse" />
          ))}
        </div>
      ) : (
        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4 sm:gap-6">
          {shows.map((show) => (
            <MediaCard key={show.id} item={show} />
          ))}
        </div>
      )}
    </div>
  );
}


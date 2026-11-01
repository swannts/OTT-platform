'use client';

import React, { useEffect, useState } from 'react';
import Link from 'next/link';
import { api } from '@/lib/api';
import { MediaItem } from '@/types';
import MediaGrid from '@/components/media/MediaGrid';
import { EmptyState } from '@/components/ui';
import { useAuth } from '@/context/AuthContext';
import { Heart, Plus } from 'lucide-react';

export default function WatchlistPage() {
  const { user } = useAuth();
  const [items, setItems] = useState<MediaItem[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    async function loadWatchlist() {
      setIsLoading(true);
      try {
        const data = await api.getWatchlist();
        if (data && Array.isArray(data)) {
          setItems(data);
        } else {
          setItems([]);
        }
      } catch {
        setItems([]);
      } finally {
        setIsLoading(false);
      }
    }

    if (user) {
      loadWatchlist();
    } else {
      setIsLoading(false);
    }
  }, [user]);

  if (!user) {
    return (
      <div className="min-h-screen pt-32 text-center text-white px-4">
        <Heart className="w-12 h-12 text-red-500 mx-auto mb-4" />
        <h2 className="text-2xl font-bold mb-2">Sign in to view your watchlist</h2>
        <p className="text-sm text-gray-400 mb-6">Save movies and shows to watch later across all your devices.</p>
        <Link
          href="/login?redirect=/watchlist"
          className="bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-8 rounded-xl"
        >
          Sign In
        </Link>
      </div>
    );
  }

  return (
    <div className="min-h-screen pt-24 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto pb-20">
      <div className="flex items-center space-x-3 mb-8 border-b border-white/10 pb-4">
        <Heart className="w-7 h-7 text-red-500 fill-current" />
        <div>
          <h1 className="text-2xl sm:text-3xl font-extrabold text-white">My Watchlist</h1>
          <p className="text-xs sm:text-sm text-gray-400">
            {items.length} titles saved for later viewing
          </p>
        </div>
      </div>

      <MediaGrid
        items={items}
        isLoading={isLoading}
        aspectRatio="backdrop"
        emptyState={
          <EmptyState
            title="Your watchlist is currently empty"
            description="Explore our curated collection and save titles to watch later."
            action={
              <Link
                href="/movies"
                className="inline-flex items-center space-x-2 bg-white/10 hover:bg-white/20 text-white text-xs font-bold px-5 py-2.5 rounded-full"
              >
                <Plus className="w-4 h-4" />
                <span>Discover Titles</span>
              </Link>
            }
          />
        }
      />
    </div>
  );
}


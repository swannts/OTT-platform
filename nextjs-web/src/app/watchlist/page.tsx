'use client';

import React, { useEffect, useState } from 'react';
import Link from 'next/link';
import { api } from '@/lib/api';
import { MediaItem } from '@/types';
import { MediaGrid } from '@/components/shared';
import { EmptyState } from '@/components/ui';
import { useAuth } from '@/context/AuthContext';
import { useLanguage } from '@/providers';
import { Heart, Plus } from 'lucide-react';

export default function WatchlistPage() {
  const { user, isLoading: isAuthLoading } = useAuth();
  const { t, isBangla } = useLanguage();
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

    if (!isAuthLoading) {
      if (user) {
        loadWatchlist();
      } else {
        setIsLoading(false);
      }
    }
  }, [user, isAuthLoading]);

  if (isAuthLoading) {
    return (
      <div className="min-h-screen pt-32 text-center text-white space-y-4">
        <div className="w-10 h-10 border-4 border-[#E5B869] border-t-transparent rounded-full animate-spin mx-auto" />
        <p className="text-sm text-gray-400">{t('loading_watchlist', 'Loading your watchlist...')}</p>
      </div>
    );
  }

  if (!user) {
    return (
      <div className="min-h-screen pt-32 text-center text-white px-4">
        <Heart className="w-12 h-12 text-[#E5B869] mx-auto mb-4" />
        <h2 className="text-2xl font-bold mb-2">{t('signin_to_view_watchlist', 'Sign in to view your watchlist')}</h2>
        <p className="text-sm text-gray-400 mb-6 max-w-md mx-auto">{t('signin_watchlist_desc', 'Save movies and shows to watch later across all your devices.')}</p>
        <Link
          href="/login?redirect=/watchlist"
          className="inline-flex items-center justify-center bg-[#E5B869] hover:bg-[#f3cf8a] text-black font-bold py-3 px-8 rounded-full shadow-lg shadow-[#E5B869]/20 transition-all cursor-pointer hover:scale-[1.02] active:scale-95"
        >
          {t('sign_in', 'Sign In')}
        </Link>
      </div>
    );
  }

  const formattedCount = isBangla
    ? items.length.toString().replace(/\d/g, (d) => '০১২৩৪৫৬৭৮৯'[parseInt(d, 10)])
    : items.length.toString();

  return (
    <div className="min-h-screen pt-24 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto pb-20">
      <div className="flex items-center space-x-3 mb-8 border-b border-white/10 pb-4">
        <Heart className="w-7 h-7 text-red-500 fill-current" />
        <div>
          <h1 className="text-2xl sm:text-3xl font-extrabold text-white">{t('my_watchlist', 'My Watchlist')}</h1>
          <p className="text-xs sm:text-sm text-gray-400">
            {formattedCount} {t('watchlist_count_suffix', 'titles saved for later viewing')}
          </p>
        </div>
      </div>

      <MediaGrid
        items={items}
        isLoading={isLoading}
        aspectRatio="backdrop"
        emptyState={
          <EmptyState
            title={t('watchlist_empty_title', 'Your watchlist is currently empty')}
            description={t('watchlist_empty_desc', 'Explore our curated collection and save titles to watch later.')}
            action={
              <Link
                href="/movies"
                className="inline-flex items-center space-x-2 bg-white/10 hover:bg-white/20 text-white text-xs font-bold px-5 py-2.5 rounded-full"
              >
                <Plus className="w-4 h-4" />
                <span>{t('discover_titles', 'Discover Titles')}</span>
              </Link>
            }
          />
        }
      />
    </div>
  );
}


'use client';

import React, { useEffect, useState } from 'react';
import { useAppDispatch, useAppSelector } from '@/store/hooks';
import { fetchTvShowsList } from '@/store/slices/tvShowsSlice';
import MediaCard from '@/components/media/MediaCard';
import { Tv, Sparkles, Filter } from 'lucide-react';

export default function TvShowsPage() {
  const dispatch = useAppDispatch();
  const { shows, isLoading, page } = useAppSelector((state) => state.tvShows);
  const [selectedCategory, setSelectedCategory] = useState<string>('all');

  useEffect(() => {
    dispatch(fetchTvShowsList({ page }));
  }, [dispatch, page]);

  const categories = [
    { id: 'all', label: 'All Series' },
    { id: 'prophetic', label: 'Prophetic Chronicles', keywords: ['prophet', 'muhammad', 'messengers', 'adam', 'ibrahim'] },
    { id: 'history', label: 'Islamic History', keywords: ['andalus', 'ottoman', 'caliph', 'companions', 'civilization', 'women'] },
    { id: 'quran', label: 'Quran & Hadith', keywords: ['quran', 'hadith', 'tafseer', 'yusuf', 'nawawi'] },
    { id: 'kids', label: 'Kids & Family', keywords: ['kids', 'noor', 'bilal', 'animals', 'children'] },
    { id: 'soul', label: 'Spiritual Wisdom', keywords: ['soul', 'akhirah', 'repentance', 'wisdom'] },
  ];

  const filteredShows = shows.filter((show) => {
    if (selectedCategory === 'all') return true;
    const cat = categories.find((c) => c.id === selectedCategory);
    if (!cat?.keywords) return true;
    const text = `${show.name || ''} ${show.description || ''}`.toLowerCase();
    return cat.keywords.some((kw) => text.includes(kw));
  });

  return (
    <div className="min-h-screen pt-24 sm:pt-28 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto pb-20 select-none">
      {/* Page Header */}
      <div className="mb-8 border-b border-white/10 pb-6">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <div className="inline-flex items-center space-x-1.5 px-3 py-1 rounded-full bg-[#E5B869]/15 border border-[#E5B869]/30 text-[#E5B869] text-xs font-bold uppercase tracking-wider mb-2.5">
              <Sparkles className="w-3.5 h-3.5 text-[#E5B869]" />
              <span>Sacred Series & Docuseries</span>
            </div>
            <h1 className="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-white flex items-center space-x-3 tracking-tight">
              <Tv className="w-7 h-7 sm:w-8 sm:h-8 text-[#E5B869]" />
              <span>Islamic TV Shows & Series</span>
            </h1>
            <p className="text-sm text-gray-400 mt-1.5 max-w-2xl leading-relaxed">
              Inspirational Prophetic chronicles, historical educational docuseries, and faith-based family series.
            </p>
          </div>

          <div className="flex items-center space-x-2 text-xs text-gray-400 bg-white/[0.04] border border-white/10 px-3.5 py-1.5 rounded-full self-start sm:self-auto">
            <span className="w-2 h-2 rounded-full bg-[#E5B869]" />
            <span className="font-semibold text-gray-200">{filteredShows.length}</span>
            <span>Series Available</span>
          </div>
        </div>

        {/* Category Filter Pills */}
        <div className="flex items-center space-x-2 overflow-x-auto no-scrollbar pt-6 pb-1">
          <Filter className="w-4 h-4 text-gray-400 flex-shrink-0 mr-1" />
          {categories.map((cat) => {
            const isSelected = selectedCategory === cat.id;
            return (
              <button
                key={cat.id}
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
      {isLoading ? (
        <div className="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6">
          {[...Array(8)].map((_, i) => (
            <div key={i} className="aspect-[16/10] bg-white/5 rounded-2xl animate-pulse min-w-0" />
          ))}
        </div>
      ) : filteredShows.length > 0 ? (
        <div className="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6">
          {filteredShows.map((show) => (
            <div key={show.id} className="min-w-0">
              <MediaCard item={show} layout="grid" aspectRatio="backdrop" />
            </div>
          ))}
        </div>
      ) : (
        <div className="text-center py-20 bg-white/[0.02] border border-white/5 rounded-3xl p-8 space-y-3">
          <Tv className="w-10 h-10 text-gray-500 mx-auto" />
          <h3 className="text-lg font-bold text-white">No series found in this category</h3>
          <p className="text-xs text-gray-400">
            Try switching to &quot;All Series&quot; to view our full sacred collection.
          </p>
          <button
            onClick={() => setSelectedCategory('all')}
            className="mt-2 px-4 py-2 bg-[#E5B869] text-black font-bold text-xs rounded-full cursor-pointer hover:bg-[#f3cf8a] transition-all outline-none focus:outline-none"
          >
            Show All Series
          </button>
        </div>
      )}
    </div>
  );
}

'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { MediaItem } from '@/types';
import { Play, Info, Plus, Check, Star } from 'lucide-react';
import { api } from '@/lib/api';
import { useAuth } from '@/context/AuthContext';

interface HeroBannerProps {
  items: MediaItem[];
}

export default function HeroBanner({ items }: HeroBannerProps) {
  const { user } = useAuth();
  const [currentIndex, setCurrentIndex] = useState(0);
  const [inWatchlist, setInWatchlist] = useState<Record<number, boolean>>({});

  useEffect(() => {
    if (!items || items.length <= 1) return;
    const timer = setInterval(() => {
      setCurrentIndex((prev) => (prev + 1) % items.length);
    }, 8000);
    return () => clearInterval(timer);
  }, [items]);

  if (!items || items.length === 0) return null;

  const current = items[currentIndex];
  const backdropUrl =
    current.banner_image ||
    current.poster_image ||
    current.poster_url ||
    'https://images.unsplash.com/photo-1578328819058-b69f3a3b0f6b?w=1600&auto=format&fit=crop&q=80';

  const type = current.type || 'movie';
  const watchUrl = `/watch/${type}/${current.id}`;
  const detailUrl = type === 'movie' ? `/movies/${current.id}` : type === 'tvshow' ? `/tv-shows/${current.id}` : watchUrl;

  const handleWatchlistToggle = async (id: number) => {
    if (!user) {
      window.location.href = '/login';
      return;
    }
    const isSaved = inWatchlist[id];
    setInWatchlist((prev) => ({ ...prev, [id]: !isSaved }));
    try {
      if (isSaved) {
        await api.removeWatchlist(id);
      } else {
        await api.saveWatchlist(id, type);
      }
    } catch {
      setInWatchlist((prev) => ({ ...prev, [id]: isSaved }));
    }
  };

  return (
    <div className="relative w-full h-[70vh] sm:h-[80vh] md:h-[85vh] overflow-hidden">
      {/* Background Backdrop Image */}
      <div className="absolute inset-0">
        <img
          src={backdropUrl}
          alt={current.name || 'Hero Banner'}
          className="w-full h-full object-cover object-center transform scale-105 transition-all duration-1000 ease-out"
        />
        {/* Cinematic Vignette Gradients */}
        <div className="absolute inset-0 bg-gradient-to-t from-[#0f1015] via-[#0f1015]/40 to-transparent" />
        <div className="absolute inset-0 bg-gradient-to-r from-[#0f1015] via-[#0f1015]/70 to-transparent w-full md:w-3/4" />
      </div>

      {/* Hero Content Container */}
      <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-full flex flex-col justify-end pb-16 sm:pb-20 z-10">
        <div className="max-w-2xl space-y-4">
          {/* Tag Badges */}
          <div className="flex items-center space-x-2 text-xs font-semibold text-gray-300">
            <span className="bg-red-600 text-white px-2 py-0.5 rounded text-[11px] uppercase tracking-wider font-bold">
              Featured
            </span>
            {current.access === 'paid' && (
              <span className="bg-amber-500 text-black px-2 py-0.5 rounded text-[11px] uppercase font-bold">
                VIP Exclusive
              </span>
            )}
            {(current.imdb_rating || current.rating) && (
              <div className="flex items-center text-amber-400 bg-black/50 backdrop-blur px-2 py-0.5 rounded">
                <Star className="w-3.5 h-3.5 fill-current mr-1" />
                <span>{current.imdb_rating || current.rating}</span>
              </div>
            )}
            {current.release_year && (
              <span className="text-gray-400">{current.release_year}</span>
            )}
            {current.duration && (
              <span className="text-gray-400">{current.duration}</span>
            )}
          </div>

          {/* Title */}
          <h1 className="text-3xl sm:text-5xl md:text-6xl font-black text-white tracking-tight leading-tight line-clamp-2">
            {current.name || current.title}
          </h1>

          {/* Description */}
          {current.description && (
            <p className="text-sm sm:text-base text-gray-300 line-clamp-3 leading-relaxed drop-shadow">
              {current.description}
            </p>
          )}

          {/* Action Buttons */}
          <div className="flex items-center space-x-3 pt-2">
            <Link
              href={watchUrl}
              className="flex items-center space-x-2 bg-red-600 hover:bg-red-700 text-white font-bold px-6 py-3 rounded-full shadow-lg shadow-red-600/40 transition-transform active:scale-95"
            >
              <Play className="w-5 h-5 fill-current" />
              <span>Watch Now</span>
            </Link>

            <Link
              href={detailUrl}
              className="flex items-center space-x-2 bg-white/20 hover:bg-white/30 text-white font-semibold px-5 py-3 rounded-full backdrop-blur transition-colors"
            >
              <Info className="w-5 h-5" />
              <span>More Info</span>
            </Link>

            <button
              onClick={() => handleWatchlistToggle(current.id)}
              className="p-3 rounded-full bg-white/10 hover:bg-white/20 text-white border border-white/20 backdrop-blur transition-colors"
              title="Add to Watchlist"
            >
              {inWatchlist[current.id] ? (
                <Check className="w-5 h-5 text-red-500" />
              ) : (
                <Plus className="w-5 h-5" />
              )}
            </button>
          </div>
        </div>

        {/* Carousel Indicators */}
        {items.length > 1 && (
          <div className="flex items-center space-x-2 mt-8">
            {items.map((_, idx) => (
              <button
                key={idx}
                onClick={() => setCurrentIndex(idx)}
                className={`h-1.5 rounded-full transition-all duration-300 ${
                  currentIndex === idx ? 'w-8 bg-red-600' : 'w-2 bg-white/30 hover:bg-white/60'
                }`}
                aria-label={`Go to slide ${idx + 1}`}
              />
            ))}
          </div>
        )}
      </div>
    </div>
  );
}


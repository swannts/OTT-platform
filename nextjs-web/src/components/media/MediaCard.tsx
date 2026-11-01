'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import { MediaItem } from '@/types';
import { useAppDispatch } from '@/store/hooks';
import { setActiveItem } from '@/store/slices/playerSlice';
import { Play, Star } from 'lucide-react';

interface MediaCardProps {
  item: MediaItem;
  rank?: number;
  aspectRatio?: 'poster' | 'backdrop';
  layout?: 'grid' | 'carousel';
}

const FALLBACK_IMAGE =
  'https://images.unsplash.com/photo-1541432901042-2d8bd64b4a9b?w=800&auto=format&fit=crop&q=80';

export default function MediaCard({
  item,
  rank,
  aspectRatio = 'backdrop',
  layout = 'grid',
}: MediaCardProps) {
  const dispatch = useAppDispatch();
  const [imgSrc, setImgSrc] = useState<string>(() => {
    const raw =
      (item.poster_image && item.poster_image.trim()) ||
      (item.poster_url && item.poster_url.trim()) ||
      (item.thumbnail_image && item.thumbnail_image.trim()) ||
      (item.banner_image && item.banner_image.trim()) ||
      FALLBACK_IMAGE;
    return raw;
  });

  const type = item.type || 'movie';
  const watchUrl = `/watch/${type}/${item.id}`;
  const detailUrl =
    type === 'movie'
      ? `/movies/${item.id}`
      : type === 'tvshow'
      ? `/tv-shows/${item.id}`
      : watchUrl;

  const subtitle =
    item.duration ||
    (type === 'tvshow' ? 'Series • 8 Episodes' : item.release_year ? `${item.release_year}` : 'Sacred Feature');

  const isCarousel = layout === 'carousel';

  return (
    <div
      className={`group relative transition-all duration-300 hover:scale-[1.02] hover:z-20 select-none cursor-pointer min-w-0 ${
        isCarousel ? 'flex-none' : 'w-full'
      }`}
    >
      <div
        className={`relative rounded-xl sm:rounded-2xl overflow-hidden bg-[#0c1218] border border-white/[0.08] group-hover:border-[#E5B869]/50 shadow-lg transition-all min-w-0 ${
          isCarousel
            ? aspectRatio === 'poster'
              ? 'w-[160px] sm:w-[190px] md:w-[220px] aspect-[2/3]'
              : 'w-[240px] sm:w-[280px] md:w-[320px] aspect-[16/10]'
            : aspectRatio === 'poster'
            ? 'w-full aspect-[2/3]'
            : 'w-full aspect-[16/10]'
        }`}
      >
        {/* Primary full-card navigation link */}
        <Link
          href={detailUrl}
          className="absolute inset-0 z-0 cursor-pointer outline-none focus:outline-none"
          aria-label={item.name || item.title || 'Media Item'}
        />

        {/* Top 10 Rank Watermark */}
        {rank !== undefined && (
          <div className="pointer-events-none absolute top-2.5 left-2.5 z-10 w-7 h-7 rounded-lg bg-[#E5B869] text-black font-black text-xs flex items-center justify-center shadow-md">
            #{rank}
          </div>
        )}

        {/* Access VIP Badge */}
        {item.access === 'paid' && (
          <div className="pointer-events-none absolute top-2.5 right-2.5 z-10 bg-[#E5B869] text-black font-extrabold text-[10px] px-2 py-0.5 rounded-full uppercase tracking-wider shadow-md">
            VIP
          </div>
        )}
        {item.access === 'pay-per-view' && (
          <div className="pointer-events-none absolute top-2.5 right-2.5 z-10 bg-amber-600 text-white font-extrabold text-[10px] px-2 py-0.5 rounded-full uppercase tracking-wider">
            Rent
          </div>
        )}

        {/* Poster / Thumbnail Image with Fallback */}
        <img
          src={imgSrc}
          alt={item.name || item.title || 'Media Poster'}
          onError={() => setImgSrc(FALLBACK_IMAGE)}
          className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105 pointer-events-none"
          loading="lazy"
        />

        {/* Bottom Dark Gradient Vignette */}
        <div className="pointer-events-none absolute inset-0 bg-gradient-to-t from-[#06090e] via-[#06090e]/65 to-transparent opacity-95 transition-opacity" />

        {/* Hover Center Play Button */}
        <div className="absolute inset-0 flex items-center justify-center pointer-events-none opacity-0 group-hover:opacity-100 transition-opacity z-10">
          <button
            onClick={() => dispatch(setActiveItem(item))}
            className="pointer-events-auto w-10 h-10 rounded-full bg-[#E5B869] hover:bg-[#f3cf8a] text-black flex items-center justify-center shadow-xl shadow-[#E5B869]/30 transform group-hover:scale-110 active:scale-95 transition-all cursor-pointer"
            aria-label={`Play ${item.name || item.title}`}
          >
            <Play className="w-4 h-4 fill-black text-black ml-0.5" />
          </button>
        </div>

        {/* Card Metadata (Bottom Content with strict containment) */}
        <div className="pointer-events-none absolute bottom-0 left-0 right-0 p-3 sm:p-3.5 text-white z-10 w-full min-w-0 overflow-hidden box-border">
          <h3
            className="font-bold text-xs sm:text-[13.5px] text-white truncate block group-hover:text-[#E5B869] transition-colors tracking-tight w-full overflow-hidden"
            title={item.name || item.title}
          >
            {item.name || item.title}
          </h3>

          <div className="flex items-center justify-between text-[11px] text-gray-400 mt-1 w-full min-w-0 gap-2">
            <span className="truncate flex-1 min-w-0">{subtitle}</span>
            {(item.imdb_rating || item.rating) && (
              <span className="flex-shrink-0 inline-flex items-center text-[#E5B869] text-[10px] font-semibold bg-black/60 px-1.5 py-0.5 rounded border border-white/10">
                <Star className="w-2.5 h-2.5 fill-[#E5B869] text-[#E5B869] mr-0.5" />
                <span>{item.imdb_rating || item.rating}</span>
              </span>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}

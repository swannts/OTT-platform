'use client';

import React from 'react';
import Link from 'next/link';
import { MediaItem } from '@/types';
import { Play, Star } from 'lucide-react';

interface MediaCardProps {
  item: MediaItem;
  rank?: number;
  aspectRatio?: 'poster' | 'backdrop';
}

export default function MediaCard({
  item,
  rank,
  aspectRatio = 'backdrop',
}: MediaCardProps) {
  const imageUrl =
    (item.poster_image && item.poster_image.trim()) ||
    (item.poster_url && item.poster_url.trim()) ||
    (item.thumbnail_image && item.thumbnail_image.trim()) ||
    (item.banner_image && item.banner_image.trim()) ||
    'https://images.unsplash.com/photo-1542838132-92c53300491e?w=700&auto=format&fit=crop&q=80';

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
    (type === 'tvshow' ? 'Series • 8 Episodes' : item.release_year ? `${item.release_year}` : 'Documentary');

  return (
    <div className="group relative flex-none transition-all duration-300 hover:scale-[1.03] hover:z-20 select-none">
      <div
        className={`relative rounded-2xl overflow-hidden bg-[#0c1218] border border-white/5 group-hover:border-[#E5B869]/40 shadow-lg transition-all ${
          aspectRatio === 'poster'
            ? 'w-[160px] sm:w-[190px] md:w-[220px] aspect-[2/3]'
            : 'w-[240px] sm:w-[280px] md:w-[320px] aspect-[16/10]'
        }`}
      >
        {/* Primary full-card navigation link */}
        <Link
          href={detailUrl}
          className="absolute inset-0 z-0"
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

        {/* Poster / Thumbnail Image */}
        <img
          src={imageUrl}
          alt={item.name || item.title || 'Media Poster'}
          className="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105 pointer-events-none"
          loading="lazy"
        />

        {/* Bottom Dark Gradient Vignette */}
        <div className="pointer-events-none absolute inset-0 bg-gradient-to-t from-[#070a0e] via-[#070a0e]/40 to-transparent opacity-90 group-hover:opacity-95 transition-opacity" />

        {/* Hover Center Play Button */}
        <div className="absolute inset-0 flex items-center justify-center pointer-events-none opacity-0 group-hover:opacity-100 transition-opacity z-10">
          <Link
            href={watchUrl}
            className="pointer-events-auto w-12 h-12 rounded-full bg-[#E5B869] hover:bg-[#f3cf8a] text-black flex items-center justify-center shadow-xl shadow-[#E5B869]/30 transform group-hover:scale-105 transition-all"
            aria-label={`Play ${item.name || item.title}`}
          >
            <Play className="w-5 h-5 fill-black text-black ml-0.5" />
          </Link>
        </div>

        {/* Card Metadata (Bottom Content) */}
        <div className="pointer-events-none absolute bottom-0 left-0 right-0 p-3 sm:p-3.5 text-white z-10">
          <h3 className="font-bold text-sm sm:text-[14.5px] line-clamp-1 group-hover:text-[#E5B869] transition-colors tracking-tight">
            {item.name || item.title}
          </h3>

          <div className="flex items-center space-x-2 text-[11px] text-gray-400 mt-0.5">
            <span>{subtitle}</span>
            {(item.imdb_rating || item.rating) && (
              <span className="flex items-center text-[#E5B869]">
                <Star className="w-3 h-3 fill-[#E5B869] text-[#E5B869] mr-0.5" />
                <span>{item.imdb_rating || item.rating}</span>
              </span>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}

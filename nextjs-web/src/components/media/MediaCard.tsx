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

export default function MediaCard({ item, rank, aspectRatio = 'poster' }: MediaCardProps) {
  const imageUrl =
    item.poster_image ||
    item.poster_url ||
    item.thumbnail_image ||
    item.banner_image ||
    'https://images.unsplash.com/photo-1536440136628-849c177e76a1?w=500&auto=format&fit=crop&q=60';
  const type = item.type || 'movie';
  const watchUrl = `/watch/${type}/${item.id}`;
  const detailUrl = type === 'movie' ? `/movies/${item.id}` : type === 'tvshow' ? `/tv-shows/${item.id}` : watchUrl;

  const genres = Array.isArray(item.genres)
    ? item.genres.map((g: any) => (typeof g === 'string' ? g : g.name)).slice(0, 2)
    : (item.genre_data || []).slice(0, 2);

  return (
    <div className="group relative flex-none transition-all duration-300 hover:scale-[1.04] hover:z-20">
      <div
        className={`relative rounded-xl overflow-hidden bg-neutral-900 shadow-lg border border-white/5 group-hover:border-white/20 transition-all ${
          aspectRatio === 'poster' ? 'w-[160px] sm:w-[190px] md:w-[220px] aspect-[2/3]' : 'w-[240px] sm:w-[280px] md:w-[320px] aspect-video'
        }`}
      >
        {/* Primary full-card navigation link (no nested anchor tags) */}
        <Link
          href={detailUrl}
          className="absolute inset-0 z-0"
          aria-label={item.name || item.title || 'Media Item'}
        />

        {/* Top 10 Rank Watermark */}
        {rank !== undefined && (
          <div className="pointer-events-none absolute top-2 left-2 z-10 w-7 h-7 rounded-md bg-red-600/90 text-white font-black text-xs flex items-center justify-center shadow-md">
            #{rank}
          </div>
        )}

        {/* Access Badge */}
        {item.access === 'paid' && (
          <div className="pointer-events-none absolute top-2 right-2 z-10 bg-amber-500/90 text-black font-extrabold text-[10px] px-2 py-0.5 rounded-full uppercase tracking-wider">
            VIP
          </div>
        )}
        {item.access === 'pay-per-view' && (
          <div className="pointer-events-none absolute top-2 right-2 z-10 bg-blue-600/90 text-white font-extrabold text-[10px] px-2 py-0.5 rounded-full uppercase tracking-wider">
            Rent
          </div>
        )}

        {/* Poster Image */}
        <img
          src={imageUrl}
          alt={item.name || item.title || 'Movie Poster'}
          className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105 pointer-events-none"
          loading="lazy"
        />

        {/* Bottom Gradient Overlay */}
        <div className="pointer-events-none absolute inset-0 bg-gradient-to-t from-black via-black/30 to-transparent opacity-80 group-hover:opacity-95 transition-opacity" />

        {/* Hover Play Button Overlay */}
        <div className="absolute inset-0 flex items-center justify-center pointer-events-none opacity-0 group-hover:opacity-100 transition-opacity z-10">
          <Link
            href={watchUrl}
            className="pointer-events-auto w-12 h-12 rounded-full bg-red-600 hover:bg-red-700 text-white flex items-center justify-center shadow-xl shadow-red-600/50 transform group-hover:scale-105 transition-transform"
            aria-label={`Play ${item.name || item.title}`}
          >
            <Play className="w-5 h-5 fill-current ml-0.5" />
          </Link>
        </div>

        {/* Card Meta Content */}
        <div className="pointer-events-none absolute bottom-0 left-0 right-0 p-3 text-white z-10">
          <h3 className="font-semibold text-sm line-clamp-1 group-hover:text-red-400 transition-colors">
            {item.name || item.title}
          </h3>

          <div className="flex items-center space-x-2 text-[11px] text-gray-400 mt-1">
            {(item.imdb_rating || item.rating) && (
              <div className="flex items-center text-amber-400">
                <Star className="w-3 h-3 fill-current mr-0.5" />
                <span>{item.imdb_rating || item.rating}</span>
              </div>
            )}
            {item.release_year && <span>{item.release_year}</span>}
            {item.duration && <span>{item.duration}</span>}
          </div>

          {genres.length > 0 && (
            <div className="flex items-center space-x-1.5 mt-1.5 text-[10px] text-gray-400">
              {genres.map((g: string, i: number) => (
                <span key={i} className="bg-white/10 px-1.5 py-0.5 rounded text-gray-300">
                  {g}
                </span>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

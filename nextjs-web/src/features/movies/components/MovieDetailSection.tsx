'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import { ContentDetails } from '@/types';
import { Play, Plus, Check, Star, Clock, Calendar } from 'lucide-react';
import { MediaRow, ReviewSection } from '@/components/shared';
import { useAuth } from '@/context/AuthContext';
import { api } from '@/lib/api';

interface MovieDetailSectionProps {
  initialMovie: ContentDetails;
}

export default function MovieDetailSection({ initialMovie }: MovieDetailSectionProps) {
  const { user } = useAuth();
  const [movie] = useState<ContentDetails>(initialMovie);
  const [inWatchlist, setInWatchlist] = useState<boolean>(initialMovie.is_watchlist || false);
  const [isUpdatingWatchlist, setIsUpdatingWatchlist] = useState(false);

  const backdropUrl =
    movie.banner_image ||
    movie.poster_image ||
    'https://images.unsplash.com/photo-1591604129939-f1efa4d9f7fa?w=1600&auto=format&fit=crop&q=80';
  const watchUrl = `/watch/movie/${movie.id}`;

  const handleToggleWatchlist = async () => {
    if (!user) {
      setInWatchlist(!inWatchlist);
      return;
    }
    setIsUpdatingWatchlist(true);
    try {
      if (inWatchlist) {
        await api.removeWatchlist(movie.id);
        setInWatchlist(false);
      } else {
        await api.saveWatchlist(movie.id, 'movie');
        setInWatchlist(true);
      }
    } catch (e) {
      console.error('Failed to toggle watchlist:', e);
      setInWatchlist(!inWatchlist);
    } finally {
      setIsUpdatingWatchlist(false);
    }
  };

  return (
    <div className="min-h-screen pb-20 select-none">
      {/* Hero Backdrop Header */}
      <div className="relative w-full h-[60vh] sm:h-[70vh] overflow-hidden">
        <img
          src={backdropUrl}
          alt={movie.name}
          className="w-full h-full object-cover object-top"
        />
        <div className="absolute inset-0 bg-gradient-to-t from-[#070a0e] via-[#070a0e]/60 to-transparent" />
        <div className="absolute inset-0 bg-gradient-to-r from-[#070a0e] via-[#070a0e]/80 to-transparent w-full md:w-2/3" />

        {/* Floating Content Info */}
        <div className="absolute bottom-10 left-0 right-0 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 z-10">
          <div className="max-w-2xl space-y-4">
            <div className="flex flex-wrap items-center gap-2 text-xs font-semibold text-gray-300">
              {movie.access === 'paid' && (
                <span className="bg-[#E5B869] text-black px-2.5 py-0.5 rounded-full font-bold">
                  VIP Required
                </span>
              )}
              {movie.imdb_rating && (
                <div className="flex items-center text-[#E5B869] bg-black/60 px-2 py-0.5 rounded border border-white/10">
                  <Star className="w-3.5 h-3.5 fill-current mr-1" />
                  <span>{movie.imdb_rating} IMDb</span>
                </div>
              )}
              {movie.release_year && (
                <div className="flex items-center text-gray-300 bg-white/10 px-2 py-0.5 rounded">
                  <Calendar className="w-3.5 h-3.5 mr-1" />
                  <span>{movie.release_year}</span>
                </div>
              )}
              {movie.duration && (
                <div className="flex items-center text-gray-300 bg-white/10 px-2 py-0.5 rounded">
                  <Clock className="w-3.5 h-3.5 mr-1" />
                  <span>{movie.duration}</span>
                </div>
              )}
            </div>

            <h1 className="text-3xl sm:text-5xl font-black text-white tracking-tight drop-shadow-md">
              {movie.name}
            </h1>

            <p className="text-sm sm:text-base text-gray-300 leading-relaxed line-clamp-3">
              {movie.description}
            </p>

            <div className="flex items-center space-x-3 pt-2">
              <Link
                href={watchUrl}
                className="flex items-center space-x-2 bg-[#E5B869] hover:bg-[#f3cf8a] text-black font-bold px-7 py-3 rounded-full shadow-lg shadow-[#E5B869]/30 transition-transform active:scale-95 cursor-pointer"
              >
                <Play className="w-5 h-5 fill-black" />
                <span>Play Movie</span>
              </Link>

              <button
                type="button"
                onClick={handleToggleWatchlist}
                disabled={isUpdatingWatchlist}
                className="flex items-center space-x-2 bg-white/10 hover:bg-white/20 text-white px-5 py-3 rounded-full border border-white/20 backdrop-blur transition-all text-sm font-semibold cursor-pointer active:scale-95"
              >
                {inWatchlist ? (
                  <Check className="w-4 h-4 text-[#E5B869]" />
                ) : (
                  <Plus className="w-4 h-4" />
                )}
                <span>{inWatchlist ? 'In Watchlist' : 'Add to Watchlist'}</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Detail Sections */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-10 space-y-12">
        {/* Storyline & Overview */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-8 bg-[#0c1218] border border-white/10 p-6 sm:p-8 rounded-2xl shadow-xl">
          <div className="md:col-span-2 space-y-4">
            <h2 className="text-lg font-bold text-white tracking-tight">Storyline</h2>
            <p className="text-sm text-gray-300 leading-relaxed">{movie.description}</p>
          </div>

          <div className="space-y-4 border-t md:border-t-0 md:border-l border-white/10 md:pl-8 pt-4 md:pt-0">
            <div>
              <span className="text-xs text-gray-400 block mb-1.5 uppercase font-semibold tracking-wider">Genres</span>
              <div className="flex flex-wrap gap-1.5">
                {(movie.genre_data || []).map((genre, i) => (
                  <span
                    key={i}
                    className="bg-white/5 border border-white/10 text-gray-200 text-xs px-2.5 py-1 rounded-md"
                  >
                    {genre}
                  </span>
                ))}
              </div>
            </div>

            {movie.casts && movie.casts.length > 0 && (
              <div>
                <span className="text-xs text-gray-400 block mb-1.5 uppercase font-semibold tracking-wider">
                  Scholars & Narrators
                </span>
                <p className="text-sm text-gray-200 font-medium">
                  {movie.casts.map((c) => c.name).join(', ')}
                </p>
              </div>
            )}
          </div>
        </div>

        {/* Viewer Reviews & Star Ratings */}
        <ReviewSection
          contentId={movie.id}
          contentType="movie"
          initialReviews={movie.reviews}
        />

        {/* More Like This / Recommended */}
        {movie.more_items && movie.more_items.length > 0 && (
          <MediaRow title="More Like This" items={movie.more_items} aspectRatio="backdrop" />
        )}
      </div>
    </div>
  );
}


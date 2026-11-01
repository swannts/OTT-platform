'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import { ContentDetails, EpisodeItem } from '@/types';
import { Play, Star, Calendar, Tv, Plus, Check } from 'lucide-react';
import { useAuth } from '@/context/AuthContext';
import { api } from '@/lib/api';

interface TvShowDetailSectionProps {
  initialShow: ContentDetails;
  initialEpisodes: EpisodeItem[];
}

export default function TvShowDetailSection({
  initialShow,
  initialEpisodes,
}: TvShowDetailSectionProps) {
  const { user } = useAuth();
  const [show] = useState<ContentDetails>(initialShow);
  const [episodes] = useState<EpisodeItem[]>(initialEpisodes);
  const [inWatchlist, setInWatchlist] = useState<boolean>(initialShow.is_watchlist || false);
  const [isUpdatingWatchlist, setIsUpdatingWatchlist] = useState(false);

  const backdropUrl =
    show.banner_image ||
    show.poster_image ||
    'https://images.unsplash.com/photo-1591604129939-f1efa4d9f7fa?w=1600&auto=format&fit=crop&q=80';

  const firstEpId = episodes[0]?.id || '1';

  const handleToggleWatchlist = async () => {
    if (!user) {
      setInWatchlist(!inWatchlist);
      return;
    }
    setIsUpdatingWatchlist(true);
    try {
      if (inWatchlist) {
        await api.removeWatchlist(show.id);
        setInWatchlist(false);
      } else {
        await api.saveWatchlist(show.id, 'tvshow');
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
      {/* Hero Header */}
      <div className="relative w-full h-[60vh] sm:h-[70vh] overflow-hidden">
        <img
          src={backdropUrl}
          alt={show.name}
          className="w-full h-full object-cover object-top"
        />
        <div className="absolute inset-0 bg-gradient-to-t from-[#070a0e] via-[#070a0e]/60 to-transparent" />
        <div className="absolute inset-0 bg-gradient-to-r from-[#070a0e] via-[#070a0e]/80 to-transparent w-full md:w-2/3" />

        <div className="absolute bottom-10 left-0 right-0 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 z-10">
          <div className="max-w-2xl space-y-4">
            <div className="flex flex-wrap items-center gap-2 text-xs font-semibold text-gray-300">
              <span className="bg-[#E5B869] text-black px-2.5 py-0.5 rounded-full font-bold uppercase tracking-wider text-[11px]">
                Islamic Series
              </span>
              {show.imdb_rating && (
                <div className="flex items-center text-[#E5B869] bg-black/60 px-2 py-0.5 rounded border border-white/10">
                  <Star className="w-3.5 h-3.5 fill-current mr-1" />
                  <span>{show.imdb_rating} IMDb</span>
                </div>
              )}
              {show.release_year && (
                <div className="flex items-center text-gray-300 bg-white/10 px-2 py-0.5 rounded">
                  <Calendar className="w-3.5 h-3.5 mr-1" />
                  <span>{show.release_year}</span>
                </div>
              )}
            </div>

            <h1 className="text-3xl sm:text-5xl font-black text-white tracking-tight drop-shadow-md">
              {show.name}
            </h1>

            <p className="text-sm sm:text-base text-gray-300 leading-relaxed line-clamp-3">
              {show.description}
            </p>

            <div className="flex items-center space-x-3 pt-2">
              <Link
                href={`/watch/episode/${firstEpId}`}
                className="flex items-center space-x-2 bg-[#E5B869] hover:bg-[#f3cf8a] text-black font-bold px-7 py-3 rounded-full shadow-lg shadow-[#E5B869]/30 transition-transform active:scale-95 cursor-pointer"
              >
                <Play className="w-5 h-5 fill-black" />
                <span>Play S1 E1</span>
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

      {/* Episodes List Section */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-12 space-y-6">
        <div className="flex items-center justify-between border-b border-white/10 pb-4">
          <h2 className="text-xl sm:text-2xl font-bold text-white flex items-center space-x-2.5">
            <Tv className="w-6 h-6 text-[#E5B869]" />
            <span>Episodes & Lectures</span>
          </h2>
          <span className="text-xs text-gray-400 bg-white/5 border border-white/10 px-3 py-1 rounded-full">
            {episodes.length} Episodes Available
          </span>
        </div>

        <div className="space-y-3 sm:space-y-4">
          {episodes.map((ep, idx) => (
            <Link
              key={ep.id || idx}
              href={`/watch/episode/${ep.id || idx + 1}`}
              className="flex flex-col sm:flex-row items-start sm:items-center justify-between p-4 sm:p-5 rounded-2xl bg-[#0c1218] border border-white/[0.08] hover:border-[#E5B869]/40 hover:bg-white/[0.03] transition-all group cursor-pointer shadow-md"
            >
              <div className="flex items-start sm:items-center space-x-4">
                <div className="w-11 h-11 rounded-full bg-[#E5B869]/15 text-[#E5B869] group-hover:bg-[#E5B869] group-hover:text-black flex items-center justify-center transition-all flex-shrink-0 mt-0.5 sm:mt-0 shadow-inner">
                  <Play className="w-4 h-4 fill-current ml-0.5" />
                </div>
                <div>
                  <h3 className="text-white font-bold text-sm sm:text-base group-hover:text-[#E5B869] transition-colors leading-snug">
                    {ep.name || `Episode ${ep.episode_number || idx + 1}`}
                  </h3>
                  {ep.description && (
                    <p className="text-xs text-gray-400 mt-1 line-clamp-2 max-w-2xl leading-relaxed">
                      {ep.description}
                    </p>
                  )}
                </div>
              </div>

              {ep.duration && (
                <span className="text-xs text-gray-400 mt-2 sm:mt-0 font-medium bg-white/5 px-2.5 py-1 rounded-md border border-white/5 flex-shrink-0">
                  {ep.duration}
                </span>
              )}
            </Link>
          ))}
        </div>
      </div>
    </div>
  );
}


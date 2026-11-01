'use client';

import React, { useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import Link from 'next/link';
import { api } from '@/lib/api';
import { ContentDetails } from '@/types';
import { Play, Plus, Check, Star, Clock, Calendar, ShieldCheck, Film } from 'lucide-react';
import MediaRow from '@/components/media/MediaRow';
import { useAuth } from '@/context/AuthContext';

export default function MovieDetailPage() {
  const params = useParams();
  const router = useRouter();
  const { user } = useAuth();
  const id = params?.id as string;

  const [movie, setMovie] = useState<ContentDetails | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [inWatchlist, setInWatchlist] = useState(false);

  useEffect(() => {
    async function fetchDetail() {
      if (!id) return;
      setIsLoading(true);
      try {
        const data = await api.getContentDetails(id, 'movie');
        setMovie(data);
      } catch {
        // Mock fallback for demo
        setMovie({
          id: parseInt(id) || 1,
          name: 'Cyber Horizon: Neon Recon',
          type: 'movie',
          description:
            'Set in 2099 Neo-Tokyo, an augmented intelligence operative embarks on an unsanctioned mission into the forbidden subterranean network to locate the source of an existential anomaly.',
          poster_image: 'https://images.unsplash.com/photo-1536440136628-849c177e76a1?w=500&auto=format&fit=crop&q=80',
          banner_image: 'https://images.unsplash.com/photo-1578328819058-b69f3a3b0f6b?w=1600&auto=format&fit=crop&q=80',
          release_year: 2026,
          duration: '2h 18m',
          imdb_rating: 8.9,
          access: 'paid',
          has_content_access: true,
          genre_data: ['Sci-Fi', 'Cyberpunk', 'Action', 'Thriller'],
          casts: [
            { id: 1, name: 'Elena Rostova', role: 'Major Anya Vance' },
            { id: 2, name: 'Kenji Takahashi', role: 'Daisuke Jin' },
            { id: 3, name: 'Marcus Sterling', role: 'Director Vance' },
          ],
        });
      } finally {
        setIsLoading(false);
      }
    }

    fetchDetail();
  }, [id]);

  if (isLoading) {
    return (
      <div className="min-h-screen pt-28 max-w-7xl mx-auto px-4 animate-pulse">
        <div className="h-[50vh] bg-white/5 rounded-3xl mb-8" />
        <div className="h-8 bg-white/5 rounded w-1/3 mb-4" />
        <div className="h-4 bg-white/5 rounded w-2/3 mb-2" />
        <div className="h-4 bg-white/5 rounded w-1/2" />
      </div>
    );
  }

  if (!movie) {
    return (
      <div className="min-h-screen pt-32 text-center text-white">
        <h2 className="text-xl font-bold">Movie not found</h2>
        <Link href="/movies" className="text-red-500 underline mt-4 inline-block">
          Back to Movies
        </Link>
      </div>
    );
  }

  const backdropUrl = movie.banner_image || movie.poster_image;
  const watchUrl = `/watch/movie/${movie.id}`;

  return (
    <div className="min-h-screen pb-20">
      {/* Hero Backdrop Header */}
      <div className="relative w-full h-[60vh] sm:h-[70vh] overflow-hidden">
        <img
          src={backdropUrl}
          alt={movie.name}
          className="w-full h-full object-cover object-top"
        />
        <div className="absolute inset-0 bg-gradient-to-t from-[#0f1015] via-[#0f1015]/60 to-transparent" />
        <div className="absolute inset-0 bg-gradient-to-r from-[#0f1015] via-[#0f1015]/80 to-transparent w-full md:w-2/3" />

        {/* Floating Content Info */}
        <div className="absolute bottom-10 left-0 right-0 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 z-10">
          <div className="max-w-2xl space-y-4">
            <div className="flex flex-wrap items-center gap-2 text-xs font-semibold text-gray-300">
              {movie.access === 'paid' && (
                <span className="bg-amber-500 text-black px-2.5 py-0.5 rounded-full font-bold">
                  VIP Required
                </span>
              )}
              {movie.imdb_rating && (
                <div className="flex items-center text-amber-400 bg-black/60 px-2 py-0.5 rounded">
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

            <h1 className="text-3xl sm:text-5xl font-black text-white tracking-tight">
              {movie.name}
            </h1>

            <p className="text-sm sm:text-base text-gray-300 leading-relaxed line-clamp-3">
              {movie.description}
            </p>

            <div className="flex items-center space-x-3 pt-2">
              <Link
                href={watchUrl}
                className="flex items-center space-x-2 bg-red-600 hover:bg-red-700 text-white font-bold px-7 py-3 rounded-full shadow-lg shadow-red-600/40 transition-transform active:scale-95"
              >
                <Play className="w-5 h-5 fill-current" />
                <span>Play Movie</span>
              </Link>

              <button
                onClick={() => setInWatchlist(!inWatchlist)}
                className="flex items-center space-x-2 bg-white/10 hover:bg-white/20 text-white px-5 py-3 rounded-full border border-white/20 backdrop-blur transition-colors text-sm font-semibold"
              >
                {inWatchlist ? <Check className="w-4 h-4 text-red-500" /> : <Plus className="w-4 h-4" />}
                <span>{inWatchlist ? 'In Watchlist' : 'Add to Watchlist'}</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Detail Sections */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-10 space-y-12">
        {/* Genres & Overview */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-8 glass-card p-6 rounded-2xl">
          <div className="md:col-span-2 space-y-4">
            <h3 className="text-lg font-bold text-white">Storyline</h3>
            <p className="text-sm text-gray-300 leading-relaxed">{movie.description}</p>
          </div>

          <div className="space-y-4 border-t md:border-t-0 md:border-l border-white/10 md:pl-8 pt-4 md:pt-0">
            <div>
              <span className="text-xs text-gray-400 block mb-1">Genres</span>
              <div className="flex flex-wrap gap-1.5">
                {(movie.genre_data || []).map((genre, i) => (
                  <span key={i} className="bg-white/10 text-white text-xs px-2.5 py-1 rounded-md">
                    {genre}
                  </span>
                ))}
              </div>
            </div>

            {movie.casts && movie.casts.length > 0 && (
              <div>
                <span className="text-xs text-gray-400 block mb-1">Starring Cast</span>
                <p className="text-sm text-gray-200">
                  {movie.casts.map((c) => c.name).join(', ')}
                </p>
              </div>
            )}
          </div>
        </div>

        {/* More Like This */}
        {movie.more_items && movie.more_items.length > 0 && (
          <MediaRow title="More Like This" items={movie.more_items} />
        )}
      </div>
    </div>
  );
}


'use client';

import React, { useEffect, useState } from 'react';
import { useParams } from 'next/navigation';
import Link from 'next/link';
import { api } from '@/lib/api';
import { ContentDetails, SeasonItem, EpisodeItem } from '@/types';
import { Play, Star, Calendar, Tv, Plus, Check } from 'lucide-react';

export default function TvShowDetailPage() {
  const params = useParams();
  const id = params?.id as string;

  const [show, setShow] = useState<ContentDetails | null>(null);
  const [selectedSeason, setSelectedSeason] = useState<number>(1);
  const [episodes, setEpisodes] = useState<EpisodeItem[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    async function fetchShowDetail() {
      if (!id) return;
      setIsLoading(true);
      try {
        const data = await api.getContentDetails(id, 'tvshow');
        setShow(data);

        // Fetch episodes for season
        const epData = await api.getEpisodes(id);
        if (epData && Array.isArray(epData)) {
          setEpisodes(epData);
        }
      } catch {
        // Fallback demo data
        setShow({
          id: parseInt(id) || 1,
          name: 'Echoes of Valhalla',
          type: 'tvshow',
          description:
            'A legendary Nordic saga reimagined across centuries. As ancient clans clash for supremacy, myth and brutal history collide in blood and snow.',
          poster_image: 'https://images.unsplash.com/photo-1518709268805-4e9042af9f23?w=500&auto=format&fit=crop&q=80',
          banner_image: 'https://images.unsplash.com/photo-1578328819058-b69f3a3b0f6b?w=1600&auto=format&fit=crop&q=80',
          release_year: 2026,
          imdb_rating: 9.1,
          access: 'paid',
          has_content_access: true,
          genre_data: ['Action', 'Drama', 'Historical', 'Mythology'],
        });

        setEpisodes([
          {
            id: 1001,
            name: 'Episode 1: Blood Oath',
            episode_number: 1,
            description: 'The chieftain gathers the council of nine as omens of betrayal ignite war across the fjord.',
            duration: '52m',
            has_content_access: true,
          },
          {
            id: 1002,
            name: 'Episode 2: The Raven Banner',
            episode_number: 2,
            description: 'A daring winter crossing tests loyalty and endurance as forces gather at the frozen pass.',
            duration: '48m',
            has_content_access: true,
          },
          {
            id: 1003,
            name: 'Episode 3: Iron & Ash',
            episode_number: 3,
            description: 'Siege lines tighten as the kingdom reveals a hidden alliance from beyond the western seas.',
            duration: '55m',
            has_content_access: true,
          },
        ]);
      } finally {
        setIsLoading(false);
      }
    }

    fetchShowDetail();
  }, [id]);

  if (isLoading || !show) {
    return (
      <div className="min-h-screen pt-28 max-w-7xl mx-auto px-4 animate-pulse">
        <div className="h-[50vh] bg-white/5 rounded-3xl mb-8" />
        <div className="h-8 bg-white/5 rounded w-1/3 mb-4" />
      </div>
    );
  }

  return (
    <div className="min-h-screen pb-20">
      {/* Hero Header */}
      <div className="relative w-full h-[60vh] sm:h-[70vh] overflow-hidden">
        <img
          src={show.banner_image || show.poster_image}
          alt={show.name}
          className="w-full h-full object-cover object-top"
        />
        <div className="absolute inset-0 bg-gradient-to-t from-[#0f1015] via-[#0f1015]/60 to-transparent" />
        <div className="absolute inset-0 bg-gradient-to-r from-[#0f1015] via-[#0f1015]/80 to-transparent w-full md:w-2/3" />

        <div className="absolute bottom-10 left-0 right-0 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 z-10">
          <div className="max-w-2xl space-y-4">
            <div className="flex items-center space-x-2 text-xs font-semibold text-gray-300">
              <span className="bg-red-600 text-white px-2 py-0.5 rounded text-[11px] uppercase font-bold">
                Series
              </span>
              {show.imdb_rating && (
                <div className="flex items-center text-amber-400 bg-black/60 px-2 py-0.5 rounded">
                  <Star className="w-3.5 h-3.5 fill-current mr-1" />
                  <span>{show.imdb_rating} IMDb</span>
                </div>
              )}
            </div>

            <h1 className="text-3xl sm:text-5xl font-black text-white tracking-tight">
              {show.name}
            </h1>

            <p className="text-sm sm:text-base text-gray-300 leading-relaxed line-clamp-3">
              {show.description}
            </p>

            <div className="flex items-center space-x-3 pt-2">
              <Link
                href={`/watch/episode/${episodes[0]?.id || 1}`}
                className="flex items-center space-x-2 bg-red-600 hover:bg-red-700 text-white font-bold px-7 py-3 rounded-full shadow-lg shadow-red-600/40"
              >
                <Play className="w-5 h-5 fill-current" />
                <span>Play S1 E1</span>
              </Link>
            </div>
          </div>
        </div>
      </div>

      {/* Episodes List Section */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-12 space-y-6">
        <div className="flex items-center justify-between border-b border-white/10 pb-4">
          <h2 className="text-xl sm:text-2xl font-bold text-white flex items-center space-x-2">
            <Tv className="w-6 h-6 text-red-600" />
            <span>Episodes</span>
          </h2>
        </div>

        <div className="space-y-4">
          {episodes.map((ep) => (
            <Link
              key={ep.id}
              href={`/watch/episode/${ep.id}`}
              className="flex flex-col sm:flex-row items-start sm:items-center justify-between p-4 rounded-xl glass-card hover:bg-white/10 transition-colors group"
            >
              <div className="flex items-center space-x-4">
                <div className="w-12 h-12 rounded-full bg-red-600/20 text-red-500 group-hover:bg-red-600 group-hover:text-white flex items-center justify-center transition-colors">
                  <Play className="w-5 h-5 fill-current ml-0.5" />
                </div>
                <div>
                  <h4 className="text-white font-semibold text-base group-hover:text-red-400 transition-colors">
                    {ep.name}
                  </h4>
                  {ep.description && (
                    <p className="text-xs text-gray-400 mt-1 line-clamp-2 max-w-xl">
                      {ep.description}
                    </p>
                  )}
                </div>
              </div>

              {ep.duration && (
                <span className="text-xs text-gray-400 mt-2 sm:mt-0 font-mono">
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


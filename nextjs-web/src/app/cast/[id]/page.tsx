'use client';

import React, { useState, useEffect } from 'react';
import { useParams } from 'next/navigation';
import Link from 'next/link';
import { api } from '@/lib/api';
import MediaCard from '@/components/media/MediaCard';
import { Star, Film, Award, ArrowLeft } from 'lucide-react';

export default function CastDetailPage() {
  const params = useParams();
  const id = params?.id as string;

  const [cast, setCast] = useState<any>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (!id) return;
    async function loadData() {
      setLoading(true);
      try {
        const res = await api.getCastDetails(id);
        setCast(res);
      } catch (err) {
        console.error('Failed to load cast member details', err);
      } finally {
        setLoading(false);
      }
    }
    loadData();
  }, [id]);

  if (loading) {
    return (
      <div className="min-h-screen pt-28 pb-16 flex items-center justify-center text-gray-500">
        Loading cast details...
      </div>
    );
  }

  if (!cast) {
    return (
      <div className="min-h-screen pt-28 pb-16 text-center max-w-xl mx-auto px-4">
        <h2 className="text-2xl font-bold text-white mb-2">Cast Member Not Found</h2>
        <p className="text-gray-400 text-sm mb-6">The actor or director details could not be found.</p>
        <Link href="/movies" className="inline-flex items-center space-x-2 text-red-500 hover:underline">
          <ArrowLeft className="w-4 h-4" />
          <span>Back to Movies</span>
        </Link>
      </div>
    );
  }

  const filmography = cast.entertainments || cast.movies || cast.works || [];

  return (
    <div className="min-h-screen pt-24 pb-20 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
      {/* Back Button */}
      <div className="mb-6">
        <Link
          href="/movies"
          className="inline-flex items-center space-x-2 text-xs uppercase tracking-wider font-semibold text-gray-400 hover:text-white transition-colors"
        >
          <ArrowLeft className="w-4 h-4" />
          <span>Back to Catalog</span>
        </Link>
      </div>

      {/* Profile Header */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-8 mb-12">
        {/* Photo */}
        <div className="flex justify-center md:justify-start">
          <div className="w-48 h-64 sm:w-56 sm:h-72 rounded-2xl overflow-hidden shadow-2xl border border-white/10 bg-neutral-900">
            <img
              src={
                cast.image ||
                cast.profile_image ||
                'https://images.unsplash.com/photo-1544717305-2782549b5136?w=500&auto=format&fit=crop&q=80'
              }
              alt={cast.name}
              className="w-full h-full object-cover"
            />
          </div>
        </div>

        {/* Bio & Details */}
        <div className="md:col-span-3 flex flex-col justify-center">
          <div className="flex items-center space-x-2 text-xs font-semibold text-red-500 uppercase tracking-wider mb-2">
            <Award className="w-4 h-4" />
            <span>{cast.type || 'Artist / Cast'}</span>
          </div>

          <h1 className="text-3xl sm:text-5xl font-black text-white tracking-tight mb-4">
            {cast.name}
          </h1>

          {cast.bio && (
            <p className="text-gray-300 text-sm sm:text-base leading-relaxed mb-6 max-w-3xl">
              {cast.bio}
            </p>
          )}

          <div className="flex flex-wrap gap-4 text-xs text-gray-400">
            {cast.born && (
              <div>
                <span className="font-semibold text-white block">Born</span>
                <span>{cast.born}</span>
              </div>
            )}
            {cast.birth_place && (
              <div>
                <span className="font-semibold text-white block">Birthplace</span>
                <span>{cast.birth_place}</span>
              </div>
            )}
            <div>
              <span className="font-semibold text-white block">Credits</span>
              <span>{filmography.length} Titles</span>
            </div>
          </div>
        </div>
      </div>

      {/* Filmography Section */}
      <div className="border-t border-white/10 pt-8">
        <div className="flex items-center space-x-2 mb-6">
          <Film className="w-5 h-5 text-red-500" />
          <h2 className="text-xl font-bold text-white">Filmography & Credits</h2>
        </div>

        {filmography.length === 0 ? (
          <p className="text-gray-500 text-sm">No titles listed for this cast member yet.</p>
        ) : (
          <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4 sm:gap-6">
            {filmography.map((item: any) => (
              <MediaCard key={item.id} item={item} aspectRatio="poster" />
            ))}
          </div>
        )}
      </div>
    </div>
  );
}


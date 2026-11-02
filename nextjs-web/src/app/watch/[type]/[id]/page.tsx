'use client';

import React, { useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ContentDetails } from '@/types';
import { VideoPlayer } from '@/features/player';
import { MediaRow } from '@/components/shared';
import { ArrowLeft, Star, Clock, Calendar } from 'lucide-react';

export default function WatchPage() {
  const params = useParams();
  const router = useRouter();
  const type = (params?.type as string) || 'movie';
  const id = params?.id as string;

  const [content, setContent] = useState<ContentDetails | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [loadError, setLoadError] = useState(false);

  useEffect(() => {
    async function loadContent() {
      if (!id) return;
      setIsLoading(true);
      try {
        const contentType = ['movie', 'tvshow', 'episode', 'video'].includes(type)
          ? (type as 'movie' | 'tvshow' | 'episode' | 'video')
          : 'movie';
        const data = await api.getContentDetails(id, contentType);
        setContent(data);
      } catch {
        setContent(null);
        setLoadError(true);
      } finally {
        setIsLoading(false);
      }
    }

    loadContent();
  }, [id, type]);

  if (isLoading || !content) {
    return (
      <div className="min-h-screen bg-black flex items-center justify-center text-white">
        {isLoading ? (
          <div className="w-12 h-12 border-4 border-red-600 border-t-transparent rounded-full animate-spin" />
        ) : (
          <p role="alert" className="text-gray-300">{loadError ? 'Unable to load this title. Please try again later.' : 'Title not found.'}</p>
        )}
      </div>
    );
  }

  const streamUrl = content.video_default_quality?.url || content.video_qualities?.[0]?.url || '';
  const urlType = content.video_default_quality?.url_type || 'HLS';

  return (
    <div className="min-h-screen bg-[#07080b] pt-20 pb-20">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        {/* Back Link */}
        <button
          onClick={() => router.back()}
          className="inline-flex items-center space-x-2 text-gray-400 hover:text-white transition-colors text-xs font-semibold py-1"
        >
          <ArrowLeft className="w-4 h-4" />
          <span>Back</span>
        </button>

        {/* Video Player Container */}
        <div className="w-full">
          <VideoPlayer
            key={`${type}:${content.id}`}
            contentId={content.id}
            contentType={type}
            title={content.name}
            videoUrl={streamUrl}
            urlType={urlType}
            qualities={content.video_qualities || []}
            hasAccess={content.has_content_access === true}
            initialTime={typeof content.watched_time === 'number' ? content.watched_time : 0}
            poster={content.banner_image || content.poster_image}
            onBack={() => router.back()}
          />
        </div>

        {/* Media Details Below Player */}
        <div className="glass-card p-6 sm:p-8 rounded-2xl space-y-4">
          <div className="flex flex-wrap items-center gap-3">
            <h1 className="text-2xl sm:text-3xl font-black text-white">{content.name}</h1>
            {content.access === 'paid' && (
              <span className="bg-amber-500 text-black text-xs font-bold px-2.5 py-0.5 rounded-full uppercase">
                VIP
              </span>
            )}
          </div>

          <div className="flex items-center space-x-4 text-xs text-gray-400 font-medium">
            {content.imdb_rating && (
              <div className="flex items-center text-amber-400">
                <Star className="w-3.5 h-3.5 fill-current mr-1" />
                <span>{content.imdb_rating} IMDb</span>
              </div>
            )}
            {content.release_year && (
              <div className="flex items-center text-gray-300">
                <Calendar className="w-3.5 h-3.5 mr-1" />
                <span>{content.release_year}</span>
              </div>
            )}
            {content.duration && (
              <div className="flex items-center text-gray-300">
                <Clock className="w-3.5 h-3.5 mr-1" />
                <span>{content.duration}</span>
              </div>
            )}
          </div>

          {content.description && (
            <p className="text-sm text-gray-300 leading-relaxed max-w-4xl">
              {content.description}
            </p>
          )}

          {content.genre_data && content.genre_data.length > 0 && (
            <div className="flex flex-wrap gap-2 pt-2">
              {content.genre_data.map((g, i) => (
                <span key={i} className="bg-white/10 text-gray-300 text-xs px-2.5 py-1 rounded-md">
                  {g}
                </span>
              ))}
            </div>
          )}
        </div>

        {/* More items */}
        {content.more_items && content.more_items.length > 0 && (
          <MediaRow title="Recommended Titles" items={content.more_items} />
        )}
      </div>
    </div>
  );
}


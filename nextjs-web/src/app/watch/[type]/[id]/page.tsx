'use client';

import React, { useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import Link from 'next/link';
import { api } from '@/lib/api';
import { ContentDetails } from '@/types';
import VideoPlayer from '@/components/player/VideoPlayer';
import MediaRow from '@/components/media/MediaRow';
import { ArrowLeft, Star, Clock, Calendar } from 'lucide-react';

export default function WatchPage() {
  const params = useParams();
  const router = useRouter();
  const type = (params?.type as string) || 'movie';
  const id = params?.id as string;

  const [content, setContent] = useState<ContentDetails | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    async function loadContent() {
      if (!id) return;
      setIsLoading(true);
      try {
        const data = await api.getContentDetails(id, type as any);
        setContent(data);
      } catch {
        // Islamic fallback content
        setContent({
          id: id || '01940000-0004-7000-8000-000000000009',
          name: 'The Life of Prophet Muhammad ﷺ',
          type: type,
          description:
            'A journey of mercy, wisdom and guidance for all humanity. Exploring the pivotal historical moments and eternal teachings of the final Messenger.',
          poster_image: 'https://images.unsplash.com/photo-1591604129939-f1efa4d9f7fa?w=800&auto=format&fit=crop&q=85',
          banner_image: 'https://images.unsplash.com/photo-1591604129939-f1efa4d9f7fa?w=1920&auto=format&fit=crop&q=85',
          access: 'free',
          has_content_access: true,
          video_default_quality: {
            quality: '1080p',
            url: 'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8',
            url_type: 'HLS',
          },
          video_qualities: [
            {
              quality: '1080p',
              url: 'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8',
              url_type: 'HLS',
            },
            {
              quality: '720p',
              url: 'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8',
              url_type: 'HLS',
            },
          ],
          genre_data: ['Prophetic Biographies', 'Islamic History'],
        });
      } finally {
        setIsLoading(false);
      }
    }

    loadContent();
  }, [id, type]);

  if (isLoading || !content) {
    return (
      <div className="min-h-screen bg-black flex items-center justify-center text-white">
        <div className="w-12 h-12 border-4 border-red-600 border-t-transparent rounded-full animate-spin" />
      </div>
    );
  }

  const streamUrl =
    content.video_default_quality?.url ||
    content.video_qualities?.[0]?.url ||
    'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8';
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
            contentId={content.id}
            contentType={type}
            title={content.name}
            videoUrl={streamUrl}
            urlType={urlType}
            qualities={content.video_qualities || []}
            hasAccess={content.has_content_access !== false}
            initialTime={typeof content.watched_time === 'number' ? content.watched_time : 0}
            poster={content.banner_image || content.poster_image}
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


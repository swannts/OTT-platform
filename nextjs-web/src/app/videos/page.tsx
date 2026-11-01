'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { api } from '@/lib/api';
import MediaCard from '@/components/media/MediaCard';
import { Play, Film } from 'lucide-react';
import { MediaItem } from '@/types';

export default function VideosPage() {
  const [videos, setVideos] = useState<MediaItem[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    async function loadData() {
      setLoading(true);
      try {
        const res = await api.getVideos(1);
        const list = Array.isArray(res) ? res : res?.data || [];
        setVideos(list);
      } catch (err) {
        console.error('Failed to load videos', err);
        setVideos([]);
      } finally {
        setLoading(false);
      }
    }
    loadData();
  }, []);

  return (
    <div className="min-h-screen pt-24 pb-20 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
      {/* Header */}
      <div className="flex items-center space-x-3 mb-2">
        <Film className="w-8 h-8 text-red-600" />
        <h1 className="text-3xl sm:text-4xl font-black text-white tracking-tight">Videos & Clips</h1>
      </div>
      <p className="text-gray-400 text-sm mb-8">
        Watch exclusive backstage footage, teasers, music clips, and short features.
      </p>

      {loading ? (
        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6 animate-pulse">
          {[...Array(8)].map((_, i) => (
            <div key={i} className="rounded-xl bg-white/5 aspect-video" />
          ))}
        </div>
      ) : videos.length === 0 ? (
        <div className="text-center py-20 glass-card rounded-2xl border border-white/10 p-8">
          <Film className="w-12 h-12 text-gray-600 mx-auto mb-3" />
          <h3 className="text-lg font-bold text-white mb-1">No Videos Available</h3>
          <p className="text-xs text-gray-400 max-w-sm mx-auto">
            Check back soon for upcoming video releases and exclusive previews.
          </p>
        </div>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
          {videos.map((item) => (
            <MediaCard
              key={item.id}
              item={{ ...item, type: 'video' }}
              aspectRatio="backdrop"
            />
          ))}
        </div>
      )}
    </div>
  );
}


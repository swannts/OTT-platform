'use client';

import React, { useState } from 'react';
import MediaCard from '@/components/media/MediaCard';
import { Sparkles, Flame } from 'lucide-react';
import { MediaItem } from '@/types';

interface VideosClientProps {
  initialVideos: MediaItem[];
}

export default function VideosClient({ initialVideos }: VideosClientProps) {
  const [videos] = useState<MediaItem[]>(initialVideos);

  return (
    <div className="min-h-screen pt-24 sm:pt-28 pb-20 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto select-none">
      {/* Header */}
      <div className="mb-8 border-b border-white/10 pb-6">
        <div className="inline-flex items-center space-x-1.5 px-3 py-1 rounded-full bg-[#E5B869]/15 border border-[#E5B869]/30 text-[#E5B869] text-xs font-bold uppercase tracking-wider mb-2.5">
          <Sparkles className="w-3.5 h-3.5 text-[#E5B869]" />
          <span>Knowledge & Spiritual Reminders</span>
        </div>
        <h1 className="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-white flex items-center space-x-3 tracking-tight">
          <Flame className="w-7 h-7 sm:w-8 sm:h-8 text-[#E5B869]" />
          <span>Islamic Lectures, Waz & Bayan</span>
        </h1>
        <p className="text-gray-400 text-sm mt-1.5 max-w-2xl leading-relaxed">
          Watch insightful scholarly discourses, Friday Khutbah reflections, prophetic wisdom, and family guidance.
        </p>
      </div>

      {videos.length === 0 ? (
        <div className="text-center py-20 bg-[#0c1218] rounded-2xl border border-white/10 p-8 shadow-xl">
          <Flame className="w-12 h-12 text-[#E5B869]/50 mx-auto mb-3" />
          <h3 className="text-lg font-bold text-white mb-1">New Lectures Coming Soon</h3>
          <p className="text-xs text-gray-400 max-w-sm mx-auto">
            Our curators are preparing new verified scholarly discourses. Please check back shortly.
          </p>
        </div>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
          {videos.map((item) => (
            <div key={item.id} className="min-w-0">
              <MediaCard
                item={{ ...item, type: 'video' }}
                aspectRatio="backdrop"
                layout="grid"
              />
            </div>
          ))}
        </div>
      )}
    </div>
  );
}


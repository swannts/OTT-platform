'use client';

import React, { useState } from 'react';
import MediaCard from '@/components/media/MediaCard';
import { Sparkles, Flame } from 'lucide-react';
import { MediaItem } from '@/types';
import { SectionHeader } from '@/components/ui';

interface VideosSectionProps {
  initialVideos: MediaItem[];
}

export default function VideosSection({ initialVideos }: VideosSectionProps) {
  const [videos] = useState<MediaItem[]>(initialVideos);

  return (
    <div className="min-h-screen pt-24 sm:pt-28 pb-20 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto select-none">
      {/* Standardized Section Header */}
      <SectionHeader
        badgeText="Knowledge & Spiritual Reminders"
        badgeIcon={<Sparkles className="w-3.5 h-3.5 text-[#E5B869]" />}
        title="Islamic Lectures, Waz & Bayan"
        titleIcon={<Flame className="w-7 h-7 sm:w-8 sm:h-8" />}
        description="Watch insightful scholarly discourses, Friday Khutbah reflections, prophetic wisdom, and family guidance."
      />

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


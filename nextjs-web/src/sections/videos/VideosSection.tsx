'use client';

import React, { useState } from 'react';
import MediaGrid from '@/components/media/MediaGrid';
import { Sparkles, Flame } from 'lucide-react';
import { MediaItem } from '@/types';
import { SectionHeader, EmptyState } from '@/components/ui';

interface VideosSectionProps {
  initialVideos: MediaItem[];
}

export default function VideosSection({ initialVideos }: VideosSectionProps) {
  const [videos] = useState<MediaItem[]>(() =>
    initialVideos.map((item) => ({ ...item, type: 'video' }))
  );

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

      <MediaGrid
        items={videos}
        aspectRatio="backdrop"
        emptyState={
          <EmptyState
            icon={<Flame className="w-10 h-10 text-[#E5B869]/50" />}
            title="New Lectures Coming Soon"
            description="Our curators are preparing new verified scholarly discourses. Please check back shortly."
          />
        }
      />
    </div>
  );
}


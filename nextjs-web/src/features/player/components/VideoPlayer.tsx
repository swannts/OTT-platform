'use client';

import React from 'react';
import CustomVideoPlayer from './CustomVideoPlayer';
import LockedPlayer from './LockedPlayer';
import { VideoQuality } from '@/types';

export interface VideoPlayerProps {
  contentId: string | number;
  contentType: string;
  title: string;
  videoUrl?: string;
  urlType?: string;
  qualities?: VideoQuality[];
  hasAccess?: boolean;
  initialTime?: number;
  poster?: string;
  isLive?: boolean;
  onBack?: () => void;
}

export default function VideoPlayer({
  contentId,
  contentType,
  title,
  videoUrl = '',
  urlType = 'URL',
  qualities = [],
  hasAccess = true,
  initialTime = 0,
  poster,
  isLive = false,
  onBack,
}: VideoPlayerProps) {
  // 1. Locked VIP Subscription State
  if (!hasAccess) {
    return <LockedPlayer title={title} poster={poster} />;
  }

  // 2. High-End Custom Video Player
  return (
    <CustomVideoPlayer
      contentId={contentId}
      contentType={contentType}
      title={title}
      videoUrl={videoUrl}
      urlType={urlType}
      qualities={qualities}
      initialTime={initialTime}
      poster={poster}
      isLive={isLive || contentType === 'livetv'}
      onBack={onBack}
    />
  );
}

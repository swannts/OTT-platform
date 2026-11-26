'use client';

import React from 'react';
import VidstackPlayer, { VidstackPlayerProps } from './VidstackPlayer';
import { VideoQuality } from '@/types';

export interface CustomVideoPlayerProps {
  contentId: string | number;
  contentType: string;
  title: string;
  videoUrl?: string;
  urlType?: string;
  qualities?: VideoQuality[];
  initialTime?: number;
  poster?: string;
  isLive?: boolean;
  onBack?: () => void;
}

export default function CustomVideoPlayer({
  contentId,
  contentType,
  title,
  videoUrl = '',
  initialTime = 0,
  poster,
  isLive = false,
  onBack,
}: CustomVideoPlayerProps) {
  return (
    <VidstackPlayer
      contentId={contentId}
      contentType={contentType}
      title={title}
      videoUrl={videoUrl}
      initialTime={initialTime}
      poster={poster}
      isLive={isLive}
      onBack={onBack}
    />
  );
}

export type { VidstackPlayerProps };

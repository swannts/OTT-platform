'use client';

import React, { useEffect, useRef, useState } from 'react';
import Link from 'next/link';
import '@videojs/react/video/skin.css';
import { VideoPlayer as VjsPlayer, VideoSkin, Video } from '@videojs/react/video';
import { HlsJsVideo } from '@videojs/react/media/hlsjs-video';
import { Lock, Sparkles, Layers } from 'lucide-react';
import { api } from '@/lib/api';
import { VideoQuality } from '@/types';
import { isYouTubeVideo } from '@/lib/youtube';
import YouTubeConnector from './YouTubeConnector';

interface VideoPlayerProps {
  contentId: string | number;
  contentType: string;
  title: string;
  videoUrl?: string;
  urlType?: string;
  qualities?: VideoQuality[];
  hasAccess?: boolean;
  initialTime?: number;
  poster?: string;
}

export default function VideoPlayer({
  contentId,
  contentType,
  title,
  videoUrl,
  urlType = 'URL',
  qualities = [],
  hasAccess = false,
  initialTime = 0,
  poster,
}: VideoPlayerProps) {
  const videoRef = useRef<HTMLVideoElement>(null);
  const [isPlaying, setIsPlaying] = useState(false);
  const [currentTime, setCurrentTime] = useState(0);
  const [duration, setDuration] = useState(0);
  const [selectedQualityUrl, setSelectedQualityUrl] = useState<string>(videoUrl || '');
  const [selectedQualityType, setSelectedQualityType] = useState<string>(urlType || '');
  const [hasAppliedInitialTime, setHasAppliedInitialTime] = useState(false);

  const isCurrentYouTube = isYouTubeVideo(selectedQualityUrl, selectedQualityType);
  const isHls =
    selectedQualityUrl.includes('.m3u8') ||
    selectedQualityType?.toLowerCase() === 'hls' ||
    urlType?.toLowerCase() === 'hls';

  // Apply initial resume timestamp once video metadata is ready
  useEffect(() => {
    if (!hasAppliedInitialTime && initialTime > 0 && videoRef.current) {
      const handleLoadedMetadata = () => {
        if (videoRef.current && initialTime > 0) {
          videoRef.current.currentTime = initialTime;
          setHasAppliedInitialTime(true);
        }
      };

      const video = videoRef.current;
      video.addEventListener('loadedmetadata', handleLoadedMetadata);
      return () => video.removeEventListener('loadedmetadata', handleLoadedMetadata);
    }
  }, [initialTime, hasAppliedInitialTime]);

  // Sync Continue Watching progress every 15 seconds
  useEffect(() => {
    if (!hasAccess || !isPlaying || currentTime <= 5 || isCurrentYouTube) return;

    const syncInterval = setInterval(() => {
      api.saveContinueWatching(
        contentId,
        contentType,
        Math.floor(currentTime),
        Math.floor(duration)
      ).catch(() => {});
    }, 15000);

    return () => clearInterval(syncInterval);
  }, [contentId, contentType, currentTime, duration, isPlaying, hasAccess, isCurrentYouTube]);

  const handleTimeUpdate = () => {
    if (videoRef.current) {
      setCurrentTime(videoRef.current.currentTime);
      setDuration(videoRef.current.duration || 0);
    }
  };

  // If user has no access, render the Lock / Subscription Prompt
  if (!hasAccess) {
    return (
      <div className="relative aspect-video w-full max-h-[85vh] bg-black rounded-2xl overflow-hidden flex items-center justify-center border border-white/10 shadow-2xl">
        {poster && (
          <img
            src={poster}
            alt={title}
            className="absolute inset-0 w-full h-full object-cover opacity-20 filter blur-sm"
          />
        )}
        <div className="relative z-10 max-w-md p-8 text-center space-y-5 glass-card rounded-2xl mx-4">
          <div className="w-16 h-16 rounded-full bg-[#E5B869]/20 border border-[#E5B869]/40 text-[#E5B869] flex items-center justify-center mx-auto shadow-lg">
            <Lock className="w-8 h-8" />
          </div>
          <div>
            <h3 className="text-xl font-bold text-white mb-2">Subscription Required</h3>
            <p className="text-sm text-gray-300">
              This sacred broadcast or series is restricted to VIP subscribers.
            </p>
          </div>
          <div className="flex flex-col sm:flex-row gap-3 pt-2">
            <Link
              href="/pricing"
              className="flex-1 bg-[#E5B869] hover:bg-[#d8a855] text-black font-bold py-3 px-6 rounded-xl transition-all shadow-lg shadow-[#E5B869]/30 flex items-center justify-center space-x-2 text-sm cursor-pointer"
            >
              <Sparkles className="w-4 h-4 fill-black" />
              <span>Unlock with Go Premium</span>
            </Link>
          </div>
        </div>
      </div>
    );
  }

  // 1. YouTube Connector Mode
  if (isCurrentYouTube) {
    return (
      <div className="space-y-3">
        <YouTubeConnector
          contentId={contentId}
          contentType={contentType}
          title={title}
          videoUrl={selectedQualityUrl}
          initialTime={initialTime}
          poster={poster}
        />

        {/* Source Switcher if multiple stream sources exist */}
        {qualities.length > 1 && (
          <div className="flex items-center justify-end space-x-2 text-xs pt-1">
            <span className="text-gray-400 font-medium">Available Sources:</span>
            {qualities.map((q, idx) => (
              <button
                key={idx}
                type="button"
                onClick={() => {
                  setSelectedQualityUrl(q.url);
                  setSelectedQualityType(q.url_type || '');
                }}
                className={`px-3 py-1 rounded-lg border transition-all cursor-pointer ${
                  selectedQualityUrl === q.url
                    ? 'bg-[#E5B869] text-black border-[#E5B869] font-bold shadow-md'
                    : 'bg-white/5 text-gray-300 border-white/10 hover:bg-white/10'
                }`}
              >
                {q.quality || (isYouTubeVideo(q.url, q.url_type) ? 'YouTube HD' : `Source ${idx + 1}`)}
              </button>
            ))}
          </div>
        )}
      </div>
    );
  }

  // 2. Generic Embedded Iframe (Vimeo, Dailymotion, etc.)
  if (urlType?.toLowerCase() === 'embedded' && videoUrl) {
    return (
      <div className="relative aspect-video w-full max-h-[85vh] bg-black rounded-2xl overflow-hidden shadow-2xl border border-white/10">
        <iframe
          src={videoUrl}
          title={title}
          className="w-full h-full"
          allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
          allowFullScreen
        />
      </div>
    );
  }

  // 3. Video.js 10 Enterprise Media Player
  const videoJsSkinStyle: React.CSSProperties = {
    width: '100%',
    aspectRatio: '16 / 9',
    maxHeight: '85vh',
    '--media-accent-color': '#E5B869',
    '--media-accent-text-color': '#000000',
    '--media-border-color': 'rgba(255, 255, 255, 0.1)',
    '--media-border-radius': '1rem',
    '--media-font-family': 'var(--font-sans), sans-serif',
  } as React.CSSProperties;

  return (
    <div className="space-y-3">
      {/* Video.js 10 Container with NOOR Cinema Styling */}
      <div className="relative aspect-video w-full max-h-[85vh] bg-black rounded-2xl overflow-hidden shadow-2xl border border-white/10 group">
        <VjsPlayer>
          <VideoSkin style={videoJsSkinStyle}>
            {isHls ? (
              <HlsJsVideo
                ref={videoRef}
                src={selectedQualityUrl}
                poster={poster}
                playsInline
                onTimeUpdate={handleTimeUpdate}
                onPlay={() => setIsPlaying(true)}
                onPause={() => setIsPlaying(false)}
                className="w-full h-full object-contain"
              />
            ) : (
              <Video
                ref={videoRef}
                src={selectedQualityUrl}
                poster={poster}
                playsInline
                onTimeUpdate={handleTimeUpdate}
                onPlay={() => setIsPlaying(true)}
                onPause={() => setIsPlaying(false)}
                className="w-full h-full object-contain"
              />
            )}
          </VideoSkin>
        </VjsPlayer>

        {/* Cinematic Title Overlay (shows on top when hovering) */}
        <div className="absolute top-0 left-0 right-0 p-4 bg-gradient-to-b from-black/80 via-black/30 to-transparent pointer-events-none opacity-0 group-hover:opacity-100 transition-opacity duration-300 z-30 flex items-center justify-between">
          <div className="flex items-center space-x-2">
            <span className="w-2 h-2 rounded-full bg-[#E5B869] shadow-[0_0_8px_#E5B869]" />
            <h2 className="text-white font-bold text-sm sm:text-base drop-shadow-md truncate max-w-lg">
              {title}
            </h2>
          </div>
          <div className="flex items-center space-x-2">
            <span className="inline-flex items-center space-x-1 px-2 py-0.5 rounded text-[10px] font-bold bg-[#E5B869]/20 text-[#E5B869] border border-[#E5B869]/40 backdrop-blur-md">
              <Layers className="w-3 h-3" />
              <span>Video.js 10 Engine</span>
            </span>
          </div>
        </div>
      </div>

      {/* Available Stream Sources / Renditions */}
      {qualities.length > 1 && (
        <div className="flex items-center justify-end space-x-2 text-xs pt-1">
          <span className="text-gray-400 font-medium">Available Sources:</span>
          {qualities.map((q, idx) => (
            <button
              key={idx}
              type="button"
              onClick={() => {
                setSelectedQualityUrl(q.url);
                setSelectedQualityType(q.url_type || '');
              }}
              className={`px-3 py-1 rounded-lg border transition-all cursor-pointer ${
                selectedQualityUrl === q.url
                  ? 'bg-[#E5B869] text-black border-[#E5B869] font-bold shadow-md'
                  : 'bg-white/5 text-gray-300 border-white/10 hover:bg-white/10'
              }`}
            >
              {q.quality || (isYouTubeVideo(q.url, q.url_type) ? 'YouTube HD' : `Source ${idx + 1}`)}
            </button>
          ))}
        </div>
      )}
    </div>
  );
}

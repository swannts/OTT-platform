'use client';

import React, { useState, useEffect, useRef } from 'react';
import { getYouTubeEmbedUrl, extractYouTubeId } from '@/lib/youtube';
import { api } from '@/lib/api';
import { ExternalLink, RefreshCw, Play, CheckCircle2, AlertCircle } from 'lucide-react';

interface YouTubeConnectorProps {
  contentId: string | number;
  contentType: string;
  title: string;
  videoUrl: string;
  initialTime?: number;
  poster?: string;
  onQualityChangeRequest?: () => void;
  availableQualitiesCount?: number;
}

export default function YouTubeConnector({
  contentId,
  contentType,
  title,
  videoUrl,
  initialTime = 0,
  poster,
}: YouTubeConnectorProps) {
  const [isLoaded, setIsLoaded] = useState(false);
  const [hasError, setHasError] = useState(false);
  const [key, setKey] = useState(0); // For reload
  const videoId = extractYouTubeId(videoUrl);

  const embedUrl = videoId
    ? getYouTubeEmbedUrl(videoId, {
        autoplay: true,
        startTime: initialTime,
        rel: false,
        controls: true,
        modestBranding: true,
        enableJsApi: true,
      })
    : null;

  // Sync Continue Watching progress every 15 seconds
  useEffect(() => {
    if (!contentId || !isLoaded) return;

    let elapsed = initialTime;
    const interval = setInterval(() => {
      elapsed += 15;
      api.saveContinueWatching(contentId, contentType, Math.floor(elapsed), 3600).catch(() => {});
    }, 15000);

    return () => clearInterval(interval);
  }, [contentId, contentType, initialTime, isLoaded]);

  if (!videoId || !embedUrl) {
    return (
      <div className="relative aspect-video w-full max-h-[85vh] bg-black rounded-2xl overflow-hidden flex flex-col items-center justify-center p-6 text-center border border-white/10 shadow-2xl">
        <AlertCircle className="w-12 h-12 text-amber-500 mb-3" />
        <h3 className="text-lg font-bold text-white mb-1">Invalid YouTube URL</h3>
        <p className="text-xs text-gray-400 max-w-sm mb-4">
          The video stream could not be loaded because the YouTube link or video ID is invalid.
        </p>
        <span className="text-[11px] font-mono text-gray-500 bg-white/5 px-3 py-1 rounded">
          {videoUrl || 'No URL specified'}
        </span>
      </div>
    );
  }

  const rawYouTubeWatchUrl = `https://www.youtube.com/watch?v=${videoId}`;

  return (
    <div
      className="relative aspect-video w-full max-h-[85vh] bg-[#05080c] rounded-2xl overflow-hidden shadow-2xl border border-[#E5B869]/20 select-none group"
      data-testid="youtube-connector"
    >
      {/* 1. Header Information Bar */}
      <div className="absolute top-0 left-0 right-0 z-20 px-4 py-3 bg-gradient-to-b from-black/85 via-black/40 to-transparent flex items-center justify-between opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-auto">
        <div className="flex items-center space-x-2.5 min-w-0">
          <div className="flex items-center space-x-1.5 bg-red-600/90 text-white text-[11px] font-bold px-2.5 py-0.5 rounded-full shadow-md flex-shrink-0">
            <Play className="w-3 h-3 fill-white" />
            <span>YouTube Stream</span>
          </div>
          <h2 className="text-white text-xs sm:text-sm font-bold truncate drop-shadow">
            {title}
          </h2>
        </div>

        <div className="flex items-center space-x-2 flex-shrink-0">
          {/* Reload / Retry */}
          <button
            onClick={() => {
              setIsLoaded(false);
              setKey((prev) => prev + 1);
            }}
            className="p-1.5 rounded-lg bg-black/60 hover:bg-[#E5B869] text-gray-300 hover:text-black border border-white/10 transition-colors cursor-pointer"
            title="Reload Video Stream"
            aria-label="Reload video"
          >
            <RefreshCw className="w-3.5 h-3.5" />
          </button>

          {/* Open in YouTube in new tab */}
          <a
            href={rawYouTubeWatchUrl}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex items-center space-x-1 px-2.5 py-1 rounded-lg bg-black/60 hover:bg-white/20 text-gray-200 hover:text-white border border-white/10 text-xs font-medium transition-colors cursor-pointer"
            title="Watch on YouTube"
          >
            <span>YouTube</span>
            <ExternalLink className="w-3 h-3 ml-0.5" />
          </a>
        </div>
      </div>

      {/* 2. Loading Skeleton & Poster Preview */}
      {!isLoaded && !hasError && (
        <div className="absolute inset-0 z-10 flex flex-col items-center justify-center bg-black/90">
          {poster && (
            <img
              src={poster}
              alt={title}
              className="absolute inset-0 w-full h-full object-cover opacity-25 filter blur-sm"
            />
          )}
          <div className="relative z-20 flex flex-col items-center space-y-3">
            <div className="w-12 h-12 rounded-full border-4 border-[#E5B869] border-t-transparent animate-spin" />
            <p className="text-xs font-semibold text-gray-300 tracking-wide">
              Connecting to YouTube Stream...
            </p>
          </div>
        </div>
      )}

      {/* 3. Embedded YouTube Iframe */}
      <iframe
        key={key}
        src={embedUrl}
        title={title}
        onLoad={() => setIsLoaded(true)}
        onError={() => setHasError(true)}
        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
        allowFullScreen
        className="w-full h-full border-0 relative z-0"
      />
    </div>
  );
}


'use client';

import React, { useEffect, useRef, useState } from 'react';
import Link from 'next/link';
import Hls from 'hls.js';
import {
  Play,
  Pause,
  Volume2,
  VolumeX,
  Maximize,
  Minimize,
  Settings,
  Lock,
  RotateCcw,
  Sparkles,
} from 'lucide-react';
import { api } from '@/lib/api';
import { VideoQuality } from '@/types';

interface VideoPlayerProps {
  contentId: number;
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
  hasAccess = true,
  initialTime = 0,
  poster,
}: VideoPlayerProps) {
  const videoRef = useRef<HTMLVideoElement>(null);
  const containerRef = useRef<HTMLDivElement>(null);
  const hlsRef = useRef<Hls | null>(null);

  const [isPlaying, setIsPlaying] = useState(false);
  const [currentTime, setCurrentTime] = useState(0);
  const [duration, setDuration] = useState(0);
  const [volume, setVolume] = useState(1);
  const [isMuted, setIsMuted] = useState(false);
  const [isFullscreen, setIsFullscreen] = useState(false);
  const [showControls, setShowControls] = useState(true);
  const [selectedQualityUrl, setSelectedQualityUrl] = useState<string>(videoUrl || '');
  const [showQualityMenu, setShowQualityMenu] = useState(false);
  const controlsTimeoutRef = useRef<NodeJS.Timeout | null>(null);

  // Initialize playback & HLS
  useEffect(() => {
    if (!hasAccess || !selectedQualityUrl || !videoRef.current) return;

    const video = videoRef.current;
    const isHlsStream = selectedQualityUrl.includes('.m3u8') || urlType?.toLowerCase() === 'hls';

    if (isHlsStream) {
      if (Hls.isSupported()) {
        const hls = new Hls({
          enableWorker: true,
          lowLatencyMode: true,
        });
        hls.loadSource(selectedQualityUrl);
        hls.attachMedia(video);
        hls.on(Hls.Events.MANIFEST_PARSED, () => {
          if (initialTime > 0) {
            video.currentTime = initialTime;
          }
        });
        hlsRef.current = hls;

        return () => {
          hls.destroy();
          hlsRef.current = null;
        };
      } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
        // Safari native HLS
        video.src = selectedQualityUrl;
        if (initialTime > 0) {
          video.currentTime = initialTime;
        }
      }
    } else {
      video.src = selectedQualityUrl;
      if (initialTime > 0) {
        video.currentTime = initialTime;
      }
    }
  }, [selectedQualityUrl, hasAccess, urlType, initialTime]);

  // Sync Continue Watching progress every 15 seconds
  useEffect(() => {
    if (!hasAccess || !isPlaying || currentTime <= 5) return;

    const syncInterval = setInterval(() => {
      api.saveContinueWatching(contentId, contentType, Math.floor(currentTime), Math.floor(duration)).catch(() => {});
    }, 15000);

    return () => clearInterval(syncInterval);
  }, [contentId, contentType, currentTime, duration, isPlaying, hasAccess]);

  // Handle Controls Visibility on Mouse Move
  const handleMouseMove = () => {
    setShowControls(true);
    if (controlsTimeoutRef.current) {
      clearTimeout(controlsTimeoutRef.current);
    }
    controlsTimeoutRef.current = setTimeout(() => {
      if (isPlaying) {
        setShowControls(false);
      }
    }, 3500);
  };

  const togglePlay = () => {
    if (!videoRef.current) return;
    if (isPlaying) {
      videoRef.current.pause();
    } else {
      videoRef.current.play();
    }
    setIsPlaying(!isPlaying);
  };

  const handleTimeUpdate = () => {
    if (videoRef.current) {
      setCurrentTime(videoRef.current.currentTime);
      setDuration(videoRef.current.duration || 0);
    }
  };

  const handleSeek = (e: React.ChangeEvent<HTMLInputElement>) => {
    const time = parseFloat(e.target.value);
    if (videoRef.current) {
      videoRef.current.currentTime = time;
      setCurrentTime(time);
    }
  };

  const handleVolumeChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const val = parseFloat(e.target.value);
    setVolume(val);
    if (videoRef.current) {
      videoRef.current.volume = val;
      videoRef.current.muted = val === 0;
      setIsMuted(val === 0);
    }
  };

  const toggleMute = () => {
    if (!videoRef.current) return;
    const nextMuted = !isMuted;
    setIsMuted(nextMuted);
    videoRef.current.muted = nextMuted;
    if (!nextMuted && volume === 0) {
      setVolume(0.5);
      videoRef.current.volume = 0.5;
    }
  };

  const toggleFullscreen = () => {
    if (!containerRef.current) return;
    if (!document.fullscreenElement) {
      containerRef.current.requestFullscreen().catch(() => {});
      setIsFullscreen(true);
    } else {
      document.exitFullscreen().catch(() => {});
      setIsFullscreen(false);
    }
  };

  const formatTime = (seconds: number) => {
    if (isNaN(seconds)) return '00:00';
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins < 10 ? '0' : ''}${mins}:${secs < 10 ? '0' : ''}${secs}`;
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
          <div className="w-16 h-16 rounded-full bg-red-600/20 border border-red-500/40 text-red-500 flex items-center justify-center mx-auto shadow-lg">
            <Lock className="w-8 h-8" />
          </div>
          <div>
            <h3 className="text-xl font-bold text-white mb-2">Subscription Required</h3>
            <p className="text-sm text-gray-300">
              This content is restricted to VIP subscribers or pay-per-view access.
            </p>
          </div>
          <div className="flex flex-col sm:flex-row gap-3 pt-2">
            <Link
              href="/pricing"
              className="flex-1 bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-6 rounded-xl transition-colors shadow-lg shadow-red-600/40 flex items-center justify-center space-x-2 text-sm"
            >
              <Sparkles className="w-4 h-4" />
              <span>Unlock with VIP</span>
            </Link>
          </div>
        </div>
      </div>
    );
  }

  // If embedded URL (YouTube, Vimeo, etc.)
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

  return (
    <div
      ref={containerRef}
      onMouseMove={handleMouseMove}
      className="relative aspect-video w-full max-h-[85vh] bg-black rounded-2xl overflow-hidden shadow-2xl border border-white/10 select-none group"
    >
      <video
        ref={videoRef}
        poster={poster}
        onTimeUpdate={handleTimeUpdate}
        onClick={togglePlay}
        playsInline
        className="w-full h-full object-contain cursor-pointer"
      />

      {/* Top Bar (Title overlay) */}
      <div
        className={`absolute top-0 left-0 right-0 p-4 bg-gradient-to-b from-black/80 via-black/40 to-transparent flex items-center justify-between transition-opacity duration-300 ${
          showControls ? 'opacity-100' : 'opacity-0 pointer-events-none'
        }`}
      >
        <h2 className="text-white font-bold text-base sm:text-lg drop-shadow">{title}</h2>
      </div>

      {/* Center Big Play Button (when paused) */}
      {!isPlaying && (
        <button
          onClick={togglePlay}
          className="absolute inset-0 m-auto w-16 h-16 rounded-full bg-red-600/90 text-white flex items-center justify-center shadow-2xl hover:scale-110 transition-transform"
        >
          <Play className="w-8 h-8 fill-current ml-1" />
        </button>
      )}

      {/* Bottom Controls Bar */}
      <div
        className={`absolute bottom-0 left-0 right-0 p-4 bg-gradient-to-t from-black/95 via-black/60 to-transparent space-y-2 transition-opacity duration-300 ${
          showControls ? 'opacity-100' : 'opacity-0 pointer-events-none'
        }`}
      >
        {/* Progress Timeline Slider */}
        <div className="relative flex items-center group/slider">
          <input
            type="range"
            min={0}
            max={duration || 100}
            value={currentTime}
            onChange={handleSeek}
            className="w-full h-1.5 bg-white/20 rounded-lg appearance-none cursor-pointer accent-red-600 focus:outline-none"
          />
        </div>

        {/* Control Buttons */}
        <div className="flex items-center justify-between text-white text-sm pt-1">
          <div className="flex items-center space-x-4">
            <button onClick={togglePlay} className="hover:text-red-500 transition-colors">
              {isPlaying ? <Pause className="w-6 h-6 fill-current" /> : <Play className="w-6 h-6 fill-current" />}
            </button>

            {/* Volume Control */}
            <div className="flex items-center space-x-2">
              <button onClick={toggleMute} className="hover:text-red-500 transition-colors">
                {isMuted || volume === 0 ? <VolumeX className="w-5 h-5" /> : <Volume2 className="w-5 h-5" />}
              </button>
              <input
                type="range"
                min={0}
                max={1}
                step={0.05}
                value={isMuted ? 0 : volume}
                onChange={handleVolumeChange}
                className="w-16 h-1 bg-white/30 rounded-lg appearance-none cursor-pointer accent-red-600"
              />
            </div>

            {/* Time Display */}
            <span className="text-xs text-gray-300 font-mono">
              {formatTime(currentTime)} / {formatTime(duration)}
            </span>
          </div>

          <div className="flex items-center space-x-3">
            {/* Resolution / Quality Switcher */}
            {qualities.length > 0 && (
              <div className="relative">
                <button
                  onClick={() => setShowQualityMenu(!showQualityMenu)}
                  className="flex items-center space-x-1 hover:text-red-500 transition-colors text-xs font-semibold px-2 py-1 rounded bg-white/10"
                >
                  <Settings className="w-4 h-4 mr-1" />
                  <span>Quality</span>
                </button>
                {showQualityMenu && (
                  <div className="absolute right-0 bottom-full mb-2 w-32 glass-card rounded-lg py-1 shadow-2xl text-xs z-50">
                    {qualities.map((q, idx) => (
                      <button
                        key={idx}
                        onClick={() => {
                          setSelectedQualityUrl(q.url);
                          setShowQualityMenu(false);
                        }}
                        className={`w-full text-left px-3 py-1.5 hover:bg-white/10 transition-colors ${
                          selectedQualityUrl === q.url ? 'text-red-500 font-bold' : 'text-gray-300'
                        }`}
                      >
                        {q.quality || `Quality ${idx + 1}`}
                      </button>
                    ))}
                  </div>
                )}
              </div>
            )}

            {/* Fullscreen Button */}
            <button onClick={toggleFullscreen} className="hover:text-red-500 transition-colors">
              {isFullscreen ? <Minimize className="w-5 h-5" /> : <Maximize className="w-5 h-5" />}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}


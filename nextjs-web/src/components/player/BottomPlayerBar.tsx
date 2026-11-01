'use client';

import React, { useEffect } from 'react';
import Link from 'next/link';
import { useAppDispatch, useAppSelector } from '@/store/hooks';
import {
  loadActiveTrack,
  togglePlay,
  toggleMute,
  setProgress,
} from '@/store/slices/playerSlice';
import {
  Play,
  Pause,
  SkipBack,
  SkipForward,
  Volume2,
  VolumeX,
  Maximize2,
  Settings,
  ExternalLink,
} from 'lucide-react';

export default function BottomPlayerBar() {
  const dispatch = useAppDispatch();
  const { activeItem, isPlaying, progress, isMuted } = useAppSelector(
    (state) => state.player
  );

  useEffect(() => {
    if (!activeItem) {
      dispatch(loadActiveTrack());
    }
  }, [dispatch, activeItem]);

  if (!activeItem) return null;

  const type = activeItem.type || 'movie';
  const watchUrl = `/watch/${type}/${activeItem.id}`;
  const thumbnail =
    (activeItem.thumbnail_image && activeItem.thumbnail_image.trim()) ||
    (activeItem.poster_image && activeItem.poster_image.trim()) ||
    (activeItem.banner_image && activeItem.banner_image.trim()) ||
    'https://images.unsplash.com/photo-1591604129939-f1efa4d9f7fa?w=200&auto=format&fit=crop&q=80';

  return (
    <div className="fixed bottom-0 left-0 right-0 z-50 bg-[#070a0e]/95 backdrop-blur-xl border-t border-white/10 px-4 sm:px-6 py-2.5 shadow-2xl select-none">
      <div className="max-w-[1600px] mx-auto flex items-center justify-between gap-4">
        {/* Left: Thumbnail & Title Info from Backend */}
        <div className="flex items-center space-x-3 min-w-0 max-w-[280px] sm:max-w-sm">
          <div className="relative w-14 h-10 rounded-lg overflow-hidden flex-shrink-0 border border-white/10 bg-neutral-900 group">
            <img
              src={thumbnail}
              alt={activeItem.name || 'Currently Playing'}
              className="w-full h-full object-cover"
            />
            <Link
              href={watchUrl}
              className="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity text-white"
            >
              <ExternalLink className="w-3.5 h-3.5" />
            </Link>
          </div>

          <div className="min-w-0">
            <Link
              href={watchUrl}
              className="text-xs sm:text-[13px] font-bold text-white hover:text-[#E5B869] transition-colors truncate block"
            >
              {activeItem.name?.toLowerCase().includes('prophet muhammad') ? (
                <span>
                  The Life of Prophet Muhammad{' '}
                  <span className="font-arabic text-xs text-[#E5B869]">ﷺ</span>
                </span>
              ) : (
                activeItem.name
              )}
            </Link>
            <p className="text-[11px] text-gray-400 truncate">
              {activeItem.name?.toLowerCase().includes('prophet muhammad')
                ? 'Episode 1 • The Early Life'
                : activeItem.duration
                ? activeItem.duration
                : type === 'tvshow'
                ? 'Series'
                : 'Feature Film'}
            </p>
          </div>
        </div>

        {/* Center: Interactive Scrubber & Time */}
        <div className="flex-1 max-w-2xl hidden md:flex items-center space-x-4">
          {/* Scrubber Progress Bar */}
          <div className="relative flex-1 py-2 cursor-pointer group">
            <div className="h-1 w-full bg-white/20 rounded-full overflow-hidden">
              <div
                className="h-full bg-[#E5B869] rounded-full transition-all relative"
                style={{ width: `${progress}%` }}
              />
            </div>
            {/* Scrubber Knob */}
            <div
              className="absolute top-1/2 -translate-y-1/2 w-3.5 h-3.5 bg-[#E5B869] rounded-full shadow-lg shadow-[#E5B869]/50 transition-transform hover:scale-125"
              style={{ left: `calc(${progress}% - 7px)` }}
            />
          </div>

          {/* Time Counter */}
          <span className="text-xs text-gray-400 font-mono flex-shrink-0 tabular-nums">
            12:34 / 45:20
          </span>
        </div>

        {/* Right: Media Controls */}
        <div className="flex items-center space-x-2 sm:space-x-3 flex-shrink-0">
          {/* Skip Back */}
          <button
            className="text-gray-400 hover:text-white p-1.5 transition-colors hidden sm:block"
            aria-label="Skip back 10 seconds"
          >
            <SkipBack className="w-4 h-4" />
          </button>

          {/* Play / Pause in Gold Circle */}
          <button
            onClick={() => dispatch(togglePlay())}
            className="w-9 h-9 rounded-full bg-[#E5B869] hover:bg-[#f3cf8a] text-black flex items-center justify-center shadow-lg shadow-[#E5B869]/25 transition-transform active:scale-95"
            aria-label={isPlaying ? 'Pause' : 'Play'}
          >
            {isPlaying ? (
              <Pause className="w-4 h-4 fill-black text-black" />
            ) : (
              <Play className="w-4 h-4 fill-black text-black ml-0.5" />
            )}
          </button>

          {/* Skip Forward */}
          <button
            className="text-gray-400 hover:text-white p-1.5 transition-colors hidden sm:block"
            aria-label="Skip forward 10 seconds"
          >
            <SkipForward className="w-4 h-4" />
          </button>

          {/* Volume */}
          <button
            onClick={() => dispatch(toggleMute())}
            className="text-gray-400 hover:text-white p-1.5 transition-colors hidden lg:block"
            aria-label="Volume"
          >
            {isMuted ? <VolumeX className="w-4 h-4" /> : <Volume2 className="w-4 h-4" />}
          </button>

          {/* Subtitles / CC */}
          <button
            className="text-gray-400 hover:text-[#E5B869] p-1.5 transition-colors text-xs font-bold border border-white/20 hover:border-[#E5B869] rounded px-1.5 py-0.5 hidden sm:block"
            aria-label="Subtitles"
          >
            CC
          </button>

          {/* Settings */}
          <button
            className="text-gray-400 hover:text-white p-1.5 transition-colors hidden sm:block"
            aria-label="Player settings"
          >
            <Settings className="w-4 h-4" />
          </button>

          {/* Fullscreen */}
          <Link
            href={watchUrl}
            className="text-gray-400 hover:text-white p-1.5 transition-colors"
            aria-label="Open full player"
          >
            <Maximize2 className="w-4 h-4" />
          </Link>
        </div>
      </div>
    </div>
  );
}

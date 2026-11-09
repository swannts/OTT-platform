'use client';

import React, { useState, useEffect, useRef } from 'react';
import Link from 'next/link';
import { useAppDispatch, useAppSelector } from '@/store/hooks';
import {
  loadActiveTrack,
  togglePlay,
  toggleMute,
  setProgress,
  setVolume,
  selectPlayerState,
} from '@/store/slices/playerSlice';
import {
  Play,
  Pause,
  RotateCcw,
  RotateCw,
  Volume2,
  VolumeX,
  Maximize2,
  X,
  ChevronDown,
  Sparkles,
  Gauge,
} from 'lucide-react';

export default function BottomPlayerBar() {
  const dispatch = useAppDispatch();
  const { activeItem, isPlaying, progress, volume, isMuted } = useAppSelector(selectPlayerState);

  const [isMinimized, setIsMinimized] = useState(false);
  const [isDismissed, setIsDismissed] = useState(false);
  const [showVolumeSlider, setShowVolumeSlider] = useState(false);
  const [playbackSpeed, setPlaybackSpeed] = useState<number>(1.0);
  const [showSpeedMenu, setShowSpeedMenu] = useState(false);
  const progressBarRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!activeItem && !isDismissed) {
      dispatch(loadActiveTrack());
    }
  }, [dispatch, activeItem, isDismissed]);

  // If dismissed or no item, render nothing
  if (isDismissed || !activeItem) return null;

  const type = activeItem.type || 'movie';
  const watchUrl = `/watch/${type}/${activeItem.id}`;
  const thumbnail =
    (activeItem.thumbnail_image && activeItem.thumbnail_image.trim()) ||
    (activeItem.poster_image && activeItem.poster_image.trim()) ||
    (activeItem.banner_image && activeItem.banner_image.trim()) ||
    '/showcase/posters/path-of-light.svg';

  const handleSeek = (e: React.MouseEvent<HTMLDivElement>) => {
    if (progressBarRef.current) {
      const rect = progressBarRef.current.getBoundingClientRect();
      const clickX = e.clientX - rect.left;
      const width = rect.width;
      const newProgress = Math.max(0, Math.min(100, Math.round((clickX / width) * 100)));
      dispatch(setProgress(newProgress));
    }
  };

  const speedOptions = [1.0, 1.25, 1.5, 1.75, 2.0];

  // 1. Minimized Floating Audio Capsule (Bottom-Right)
  if (isMinimized) {
    return (
      <div className="fixed bottom-6 right-6 z-50 select-none animate-fade-in-up">
        <div
          onClick={() => setIsMinimized(false)}
          className="group flex items-center space-x-3.5 bg-[#0a1017]/95 hover:bg-[#111a26] border border-[#E5B869]/40 hover:border-[#E5B869] rounded-full p-2 pr-4 shadow-[0_12px_36px_rgba(0,0,0,0.8)] backdrop-blur-2xl cursor-pointer transition-all hover:scale-105"
        >
          <div className="relative w-11 h-11 rounded-full overflow-hidden border border-[#E5B869]/40 flex-shrink-0 shadow-md">
            <img
              src={thumbnail}
              alt={activeItem.name || 'Mini player'}
              className="w-full h-full object-cover"
            />
            {isPlaying && (
              <span className="absolute inset-0 bg-black/40 flex items-center justify-center">
                <span className="w-2.5 h-2.5 rounded-full bg-[#E5B869] animate-ping" />
              </span>
            )}
          </div>

          <div className="max-w-[150px] min-w-0">
            <p className="text-xs font-bold text-white truncate group-hover:text-[#E5B869] transition-colors">
              {activeItem.name}
            </p>
            <div className="flex items-center space-x-1.5 mt-0.5">
              <span className="w-1.5 h-1.5 rounded-full bg-[#E5B869] animate-pulse" />
              <p className="text-[10px] text-gray-400 font-medium">Tap to expand</p>
            </div>
          </div>

          <button
            onClick={(e) => {
              e.stopPropagation();
              dispatch(togglePlay());
            }}
            className="w-8 h-8 rounded-full bg-gradient-to-tr from-[#d8a855] to-[#E5B869] hover:from-[#f3cf8a] hover:to-[#E5B869] text-black flex items-center justify-center shadow-md hover:scale-110 active:scale-95 transition-transform cursor-pointer"
            aria-label={isPlaying ? 'Pause' : 'Play'}
          >
            {isPlaying ? (
              <Pause className="w-3.5 h-3.5 fill-black" />
            ) : (
              <Play className="w-3.5 h-3.5 fill-black ml-0.5" />
            )}
          </button>
        </div>
      </div>
    );
  }

  // 2. Full Floating Audio & Media Dock (Island Pill)
  return (
    <div className="fixed bottom-5 left-1/2 -translate-x-1/2 z-50 w-[94%] max-w-4xl select-none animate-fade-in-up">
      <div className="relative rounded-2xl bg-[#090e15]/95 backdrop-blur-2xl border border-[#E5B869]/30 shadow-[0_20px_50px_rgba(0,0,0,0.85)] overflow-hidden">
        {/* Top Interactive Progress Scrubber Line */}
        <div
          ref={progressBarRef}
          onClick={handleSeek}
          className="relative w-full h-1.5 bg-white/10 hover:h-2.5 transition-all cursor-pointer group"
          title="Click to seek"
        >
          <div
            className="h-full bg-gradient-to-r from-[#d8a855] via-[#E5B869] to-[#f4d48f] relative shadow-[0_0_12px_rgba(229,184,105,0.7)]"
            style={{ width: `${progress}%` }}
          />
          {/* Draggable Knob on hover */}
          <div
            className="absolute top-1/2 -translate-y-1/2 w-3.5 h-3.5 rounded-full bg-[#E5B869] border-2 border-black shadow-lg opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer"
            style={{ left: `calc(${progress}% - 7px)` }}
          />
        </div>

        {/* Floating Player Body */}
        <div className="px-3.5 sm:px-5 py-2.5 sm:py-3 flex items-center justify-between gap-3 sm:gap-6">
          {/* Left: Track Thumbnail, Title & Live Equalizer */}
          <div className="flex items-center space-x-3.5 min-w-0 flex-1 sm:flex-initial sm:max-w-xs md:max-w-sm">
            <div className="relative w-12 h-12 rounded-xl overflow-hidden flex-shrink-0 border border-[#E5B869]/30 shadow-md group">
              <img
                src={thumbnail}
                alt={activeItem.name || 'Currently Playing'}
                className="w-full h-full object-cover"
              />
              <Link
                href={watchUrl}
                className="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity text-[#E5B869] cursor-pointer"
                aria-label="Open full watch player"
              >
                <Maximize2 className="w-4 h-4" />
              </Link>
            </div>

            <div className="min-w-0">
              <div className="flex items-center space-x-1.5">
                <Link
                  href={watchUrl}
                  className="text-xs sm:text-[13.5px] font-bold text-white hover:text-[#E5B869] transition-colors truncate block cursor-pointer"
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
              </div>

              {/* Subtitle with Animated Equalizer Soundwave */}
              <div className="flex items-center space-x-2 mt-0.5">
                {/* 4 Animated Equalizer Bars */}
                <div
                  className="flex items-end space-x-0.5 h-3.5 w-4 flex-shrink-0"
                  title="Audio streaming"
                >
                  <span
                    className={`w-0.5 bg-[#E5B869] rounded-full ${
                      isPlaying ? 'animate-eq-1' : 'h-1'
                    }`}
                  />
                  <span
                    className={`w-0.5 bg-[#E5B869] rounded-full ${
                      isPlaying ? 'animate-eq-2' : 'h-2'
                    }`}
                  />
                  <span
                    className={`w-0.5 bg-[#E5B869] rounded-full ${
                      isPlaying ? 'animate-eq-3' : 'h-1.5'
                    }`}
                  />
                  <span
                    className={`w-0.5 bg-[#E5B869] rounded-full ${
                      isPlaying ? 'animate-eq-4' : 'h-2.5'
                    }`}
                  />
                </div>

                <p className="text-[11px] text-gray-400 truncate">
                  {activeItem.name?.toLowerCase().includes('prophet muhammad')
                    ? 'Episode 1 • The Early Life'
                    : activeItem.duration
                    ? activeItem.duration
                    : type === 'tvshow'
                    ? 'Sacred Audio Series'
                    : 'Sacred Audio Stream'}
                </p>
              </div>
            </div>
          </div>

          {/* Center: Playback Transport Controls */}
          <div className="flex flex-col items-center justify-center flex-shrink-0">
            <div className="flex items-center space-x-2 sm:space-x-3.5">
              {/* Playback Speed Pill */}
              <div className="relative hidden md:block">
                <button
                  onClick={() => setShowSpeedMenu(!showSpeedMenu)}
                  className="text-[11px] font-semibold text-gray-300 hover:text-[#E5B869] px-2 py-0.5 rounded-full border border-white/10 hover:border-[#E5B869]/40 bg-white/5 transition-all cursor-pointer"
                  title="Change playback speed"
                >
                  {playbackSpeed}x
                </button>
                {showSpeedMenu && (
                  <div className="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 bg-[#0c1218] border border-white/10 rounded-xl p-1.5 shadow-xl flex flex-col space-y-1 min-w-[70px]">
                    {speedOptions.map((speed) => (
                      <button
                        key={speed}
                        onClick={() => {
                          setPlaybackSpeed(speed);
                          setShowSpeedMenu(false);
                        }}
                        className={`text-[11px] py-1 px-2 rounded-lg text-center transition-colors cursor-pointer ${
                          playbackSpeed === speed
                            ? 'bg-[#E5B869] text-black font-bold'
                            : 'text-gray-300 hover:bg-white/10'
                        }`}
                      >
                        {speed}x
                      </button>
                    ))}
                  </div>
                )}
              </div>

              {/* Skip Back 10s */}
              <button
                onClick={() => dispatch(setProgress(Math.max(0, progress - 5)))}
                className="text-gray-400 hover:text-white p-1.5 transition-colors cursor-pointer hover:scale-110 active:scale-95"
                aria-label="Rewind 10 seconds"
                title="Rewind 10 seconds"
              >
                <RotateCcw className="w-4 h-4" />
              </button>

              {/* Central Glowing Golden Play / Pause Button */}
              <button
                onClick={() => dispatch(togglePlay())}
                className="w-10 h-10 sm:w-11 sm:h-11 rounded-full bg-gradient-to-tr from-[#d8a855] to-[#E5B869] hover:from-[#f3cf8a] hover:to-[#E5B869] text-black flex items-center justify-center shadow-lg shadow-[#E5B869]/30 hover:scale-105 active:scale-95 transition-all cursor-pointer"
                aria-label={isPlaying ? 'Pause' : 'Play'}
              >
                {isPlaying ? (
                  <Pause className="w-5 h-5 fill-black text-black" />
                ) : (
                  <Play className="w-5 h-5 fill-black text-black ml-0.5" />
                )}
              </button>

              {/* Skip Forward 10s */}
              <button
                onClick={() => dispatch(setProgress(Math.min(100, progress + 5)))}
                className="text-gray-400 hover:text-white p-1.5 transition-colors cursor-pointer hover:scale-110 active:scale-95"
                aria-label="Fast forward 10 seconds"
                title="Fast forward 10 seconds"
              >
                <RotateCw className="w-4 h-4" />
              </button>
            </div>

            {/* Timestamps */}
            <span className="text-[10px] sm:text-[11px] text-gray-400 font-mono tracking-tight tabular-nums mt-0.5">
              12:34 / 45:20
            </span>
          </div>

          {/* Right: Sound, Expand & Window Controls */}
          <div className="flex items-center space-x-1.5 sm:space-x-3 flex-shrink-0">
            {/* Volume Control with Popover Slider */}
            <div
              className="relative hidden sm:flex items-center"
              onMouseEnter={() => setShowVolumeSlider(true)}
              onMouseLeave={() => setShowVolumeSlider(false)}
            >
              <button
                onClick={() => dispatch(toggleMute())}
                className="text-gray-400 hover:text-white p-1.5 transition-colors cursor-pointer hover:scale-110"
                aria-label={isMuted ? 'Unmute' : 'Mute'}
              >
                {isMuted || volume === 0 ? (
                  <VolumeX className="w-4 h-4 text-red-400" />
                ) : (
                  <Volume2 className="w-4 h-4" />
                )}
              </button>

              {showVolumeSlider && (
                <div className="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 bg-[#0c1218] border border-white/10 rounded-xl p-2.5 shadow-xl flex items-center">
                  <input
                    type="range"
                    min="0"
                    max="100"
                    value={isMuted ? 0 : volume}
                    onChange={(e) => dispatch(setVolume(Number(e.target.value)))}
                    className="w-20 accent-[#E5B869] cursor-pointer h-1.5"
                  />
                </div>
              )}
            </div>

            {/* Full Watch View */}
            <Link
              href={watchUrl}
              className="text-gray-400 hover:text-[#E5B869] p-1.5 transition-colors hidden sm:block cursor-pointer hover:scale-110"
              aria-label="Open fullscreen watch page"
              title="Open full video player"
            >
              <Maximize2 className="w-4 h-4" />
            </Link>

            {/* Minimize to Dock Bubble */}
            <button
              onClick={() => setIsMinimized(true)}
              className="text-gray-400 hover:text-white p-1.5 transition-colors cursor-pointer hover:scale-110"
              aria-label="Minimize player"
              title="Minimize to floating bubble"
            >
              <ChevronDown className="w-4 h-4" />
            </button>

            {/* Close / Dismiss */}
            <button
              onClick={() => {
                if (isPlaying) dispatch(togglePlay());
                setIsDismissed(true);
              }}
              className="text-gray-400 hover:text-red-400 p-1.5 transition-colors cursor-pointer hover:scale-110"
              aria-label="Dismiss player"
              title="Close player"
            >
              <X className="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

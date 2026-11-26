'use client';

import React, { useState } from 'react';
import {
  Play,
  Pause,
  RotateCcw,
  RotateCw,
  Volume2,
  Volume1,
  VolumeX,
  Maximize,
  Minimize,
  PictureInPicture,
} from 'lucide-react';
import PlayerTimeline from './PlayerTimeline';
import QualityMenu, { HlsQualityItem } from './QualityMenu';
import { formatTime, formatRemainingTime } from '../utils/formatTime';
import { VideoQuality } from '@/types';

interface PlayerControlsProps {
  isPlaying: boolean;
  onTogglePlay: () => void;
  currentTime: number;
  duration: number;
  bufferedTime?: number;
  onSeek: (time: number) => void;
  onSeekingChange?: (seeking: boolean) => void;
  onRewind10: () => void;
  onForward10: () => void;
  volume: number; // 0 to 1
  isMuted: boolean;
  onVolumeChange: (vol: number) => void;
  onToggleMute: () => void;
  playbackRate: number;
  onRateChange: (rate: number) => void;
  qualities?: VideoQuality[];
  selectedQualityUrl: string;
  onQualityChange: (url: string, urlType: string) => void;
  hlsQualities?: HlsQualityItem[];
  isFullscreen: boolean;
  onToggleFullscreen: () => void;
  onTogglePip?: () => void;
  isPipSupported?: boolean;
  isLive?: boolean;
}

export default function PlayerControls({
  isPlaying,
  onTogglePlay,
  currentTime,
  duration,
  bufferedTime = 0,
  onSeek,
  onSeekingChange,
  onRewind10,
  onForward10,
  volume,
  isMuted,
  onVolumeChange,
  onToggleMute,
  playbackRate,
  onRateChange,
  qualities = [],
  selectedQualityUrl,
  onQualityChange,
  hlsQualities = [],
  isFullscreen,
  onToggleFullscreen,
  onTogglePip,
  isPipSupported = false,
  isLive = false,
}: PlayerControlsProps) {
  const [showRemaining, setShowRemaining] = useState(false);
  const [isVolumeHovered, setIsVolumeHovered] = useState(false);

  const effectiveVolume = isMuted ? 0 : volume;

  const VolumeIcon = isMuted || volume === 0 ? VolumeX : volume < 0.5 ? Volume1 : Volume2;

  return (
    <div className="absolute bottom-0 left-0 right-0 z-20 px-4 sm:px-6 pb-4 sm:pb-6 pt-12 bg-gradient-to-t from-black/95 via-black/60 to-transparent flex flex-col justify-end pointer-events-auto transition-opacity duration-300">
      {/* 1. Scrubber Timeline */}
      <PlayerTimeline
        currentTime={currentTime}
        duration={duration}
        bufferedTime={bufferedTime}
        onSeek={onSeek}
        onSeekingChange={onSeekingChange}
        isLive={isLive}
      />

      {/* 2. Control Buttons Bar */}
      <div className="flex items-center justify-between pt-1">
        {/* Left Side: Playback, Skip, Volume, Time */}
        <div className="flex items-center space-x-2 sm:space-x-3">
          {/* Play / Pause Primary Button */}
          <button
            type="button"
            onClick={onTogglePlay}
            className="p-2 sm:p-2.5 rounded-full bg-[#E5B869] text-black hover:bg-[#ffe4a0] shadow-[0_0_15px_rgba(229,184,105,0.4)] transition-all transform hover:scale-110 active:scale-95 cursor-pointer"
            title={isPlaying ? 'Pause (Space)' : 'Play (Space)'}
            aria-label={isPlaying ? 'Pause' : 'Play'}
          >
            {isPlaying ? (
              <Pause className="w-5 h-5 sm:w-5 sm:h-5 fill-current" />
            ) : (
              <Play className="w-5 h-5 sm:w-5 sm:h-5 fill-current ml-0.5" />
            )}
          </button>

          {/* Quick Skip Backward 10s */}
          {!isLive && (
            <button
              type="button"
              onClick={onRewind10}
              className="p-2 rounded-xl text-gray-300 hover:text-white hover:bg-white/10 transition-colors cursor-pointer"
              title="Rewind 10 Seconds (←)"
              aria-label="Rewind 10s"
            >
              <RotateCcw className="w-4 h-4 sm:w-5 sm:h-5" />
            </button>
          )}

          {/* Quick Skip Forward 10s */}
          {!isLive && (
            <button
              type="button"
              onClick={onForward10}
              className="p-2 rounded-xl text-gray-300 hover:text-white hover:bg-white/10 transition-colors cursor-pointer"
              title="Fast Forward 10 Seconds (→)"
              aria-label="Forward 10s"
            >
              <RotateCw className="w-4 h-4 sm:w-5 sm:h-5" />
            </button>
          )}

          {/* Volume Control with Hover Slider */}
          <div
            className="flex items-center"
            onMouseEnter={() => setIsVolumeHovered(true)}
            onMouseLeave={() => setIsVolumeHovered(false)}
          >
            <button
              type="button"
              onClick={onToggleMute}
              className="p-2 rounded-xl text-gray-300 hover:text-white hover:bg-white/10 transition-colors cursor-pointer"
              title={isMuted ? 'Unmute (M)' : 'Mute (M)'}
              aria-label={isMuted ? 'Unmute' : 'Mute'}
            >
              <VolumeIcon className="w-5 h-5" />
            </button>

            {/* Smooth Expanding Horizontal Slider */}
            <div
              className={`overflow-hidden transition-all duration-200 flex items-center ${
                isVolumeHovered ? 'w-20 sm:w-24 px-2 opacity-100' : 'w-0 px-0 opacity-0'
              }`}
            >
              <input
                type="range"
                min="0"
                max="1"
                step="0.05"
                value={effectiveVolume}
                onChange={(e) => onVolumeChange(parseFloat(e.target.value))}
                className="w-full h-1.5 bg-white/20 rounded-lg appearance-none cursor-pointer accent-[#E5B869]"
                aria-label="Volume Slider"
              />
            </div>
          </div>

          {/* Time Counter Display */}
          {!isLive && (
            <button
              type="button"
              onClick={() => setShowRemaining(!showRemaining)}
              className="hidden sm:inline-flex items-center text-xs font-mono font-medium text-gray-300 hover:text-white px-2 py-1 rounded cursor-pointer"
              title="Toggle Remaining / Elapsed Time"
            >
              <span>{formatTime(currentTime)}</span>
              <span className="text-gray-500 mx-1">/</span>
              <span className="text-gray-400">
                {showRemaining ? formatRemainingTime(currentTime, duration) : formatTime(duration)}
              </span>
            </button>
          )}
        </div>

        {/* Right Side: Quality, PiP, Fullscreen */}
        <div className="flex items-center space-x-1 sm:space-x-2">
          {/* Settings Menu (Speed & Quality) */}
          <QualityMenu
            playbackRate={playbackRate}
            onRateChange={onRateChange}
            qualities={qualities}
            selectedQualityUrl={selectedQualityUrl}
            onQualityChange={onQualityChange}
            hlsQualities={hlsQualities}
          />

          {/* Picture in Picture */}
          {isPipSupported && onTogglePip && (
            <button
              type="button"
              onClick={onTogglePip}
              className="p-2 rounded-xl text-gray-300 hover:text-white hover:bg-white/10 transition-colors cursor-pointer hidden sm:inline-flex"
              title="Picture in Picture (P)"
              aria-label="Picture in Picture"
            >
              <PictureInPicture className="w-5 h-5" />
            </button>
          )}

          {/* Fullscreen Toggle */}
          <button
            type="button"
            onClick={onToggleFullscreen}
            className="p-2 rounded-xl text-gray-300 hover:text-white hover:bg-white/10 transition-colors cursor-pointer"
            title={isFullscreen ? 'Exit Fullscreen (F)' : 'Fullscreen (F)'}
            aria-label={isFullscreen ? 'Exit Fullscreen' : 'Enter Fullscreen'}
          >
            {isFullscreen ? (
              <Minimize className="w-5 h-5 text-[#E5B869]" />
            ) : (
              <Maximize className="w-5 h-5" />
            )}
          </button>
        </div>
      </div>
    </div>
  );
}


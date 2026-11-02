'use client';

import React, { useRef, useState, useCallback, useEffect } from 'react';
import { formatTime } from '../utils/formatTime';

interface PlayerTimelineProps {
  currentTime: number;
  duration: number;
  bufferedTime?: number;
  onSeek: (time: number) => void;
  isLive?: boolean;
}

export default function PlayerTimeline({
  currentTime,
  duration,
  bufferedTime = 0,
  onSeek,
  isLive = false,
}: PlayerTimelineProps) {
  const progressBarRef = useRef<HTMLDivElement>(null);
  const [isHovering, setIsHovering] = useState(false);
  const [hoverPosition, setHoverPosition] = useState(0); // 0 to 1
  const [isDragging, setIsDragging] = useState(false);

  const playedRatio = duration > 0 ? Math.min(1, Math.max(0, currentTime / duration)) : 0;
  const bufferedRatio = duration > 0 ? Math.min(1, Math.max(0, bufferedTime / duration)) : 0;

  const calculateRatioFromEvent = useCallback((clientX: number) => {
    if (!progressBarRef.current) return 0;
    const rect = progressBarRef.current.getBoundingClientRect();
    const pos = (clientX - rect.left) / rect.width;
    return Math.min(1, Math.max(0, pos));
  }, []);

  const handlePointerDown = (e: React.PointerEvent<HTMLDivElement>) => {
    if (isLive || duration <= 0) return;
    e.preventDefault();
    setIsDragging(true);
    const ratio = calculateRatioFromEvent(e.clientX);
    onSeek(ratio * duration);
  };

  const handlePointerMove = (e: React.PointerEvent<HTMLDivElement>) => {
    if (isLive || duration <= 0) return;
    const ratio = calculateRatioFromEvent(e.clientX);
    setHoverPosition(ratio);
    if (isDragging) {
      onSeek(ratio * duration);
    }
  };

  // Global mouseup/mousemove when dragging
  useEffect(() => {
    if (!isDragging) return;

    const handleGlobalMove = (e: MouseEvent) => {
      const ratio = calculateRatioFromEvent(e.clientX);
      onSeek(ratio * duration);
    };

    const handleGlobalUp = () => {
      setIsDragging(false);
    };

    window.addEventListener('mousemove', handleGlobalMove);
    window.addEventListener('mouseup', handleGlobalUp);
    return () => {
      window.removeEventListener('mousemove', handleGlobalMove);
      window.removeEventListener('mouseup', handleGlobalUp);
    };
  }, [isDragging, duration, onSeek, calculateRatioFromEvent]);

  if (isLive) {
    return (
      <div className="w-full py-2 select-none">
        <div className="h-1.5 w-full bg-white/10 rounded-full overflow-hidden relative">
          <div className="h-full bg-red-600 rounded-full w-full animate-pulse" />
        </div>
      </div>
    );
  }

  const hoverTime = hoverPosition * duration;

  return (
    <div
      ref={progressBarRef}
      onPointerDown={handlePointerDown}
      onPointerMove={handlePointerMove}
      onMouseEnter={() => setIsHovering(true)}
      onMouseLeave={() => setIsHovering(false)}
      className="relative w-full py-3 select-none cursor-pointer group flex items-center"
      role="slider"
      aria-label="Video progress timeline"
      aria-valuemin={0}
      aria-valuemax={duration}
      aria-valuenow={currentTime}
      tabIndex={0}
    >
      {/* Hover Time Tooltip Bubble */}
      {isHovering && duration > 0 && (
        <div
          className="absolute -top-7 -translate-x-1/2 px-2 py-0.5 rounded-md bg-[#070a0e]/95 text-white border border-[#E5B869]/40 text-[11px] font-mono font-bold tracking-tight shadow-xl pointer-events-none z-30 transition-all duration-75"
          style={{ left: `${hoverPosition * 100}%` }}
        >
          {formatTime(hoverTime)}
        </div>
      )}

      {/* Main Track Container */}
      <div className="relative w-full h-1.5 group-hover:h-2 bg-white/15 rounded-full overflow-hidden transition-all duration-200">
        {/* Buffered Progress */}
        <div
          className="absolute top-0 bottom-0 left-0 bg-white/30 rounded-full transition-all duration-300"
          style={{ width: `${bufferedRatio * 100}%` }}
        />

        {/* Hover Ghost Track */}
        {isHovering && (
          <div
            className="absolute top-0 bottom-0 left-0 bg-white/20 rounded-full pointer-events-none"
            style={{ width: `${hoverPosition * 100}%` }}
          />
        )}

        {/* Played Progress Bar (Gold gradient) */}
        <div
          className="absolute top-0 bottom-0 left-0 bg-gradient-to-r from-[#ffe4a0] via-[#E5B869] to-[#d49f39] rounded-full shadow-[0_0_10px_rgba(229,184,105,0.6)]"
          style={{ width: `${playedRatio * 100}%` }}
        />
      </div>

      {/* Scrub Thumb Handle */}
      <div
        className={`absolute top-1/2 -translate-y-1/2 -translate-x-1/2 w-4 h-4 rounded-full bg-white border-2 border-[#E5B869] shadow-[0_0_12px_#E5B869] pointer-events-none transition-transform duration-150 ${
          isHovering || isDragging ? 'scale-100 opacity-100' : 'scale-0 opacity-0 group-hover:scale-100 group-hover:opacity-100'
        }`}
        style={{ left: `${playedRatio * 100}%` }}
      />
    </div>
  );
}


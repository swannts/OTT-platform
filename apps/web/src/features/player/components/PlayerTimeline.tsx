'use client';

import React, { useRef, useState, useCallback, useEffect } from 'react';
import { formatTime } from '../utils/formatTime';

interface PlayerTimelineProps {
  currentTime: number;
  duration: number;
  bufferedTime?: number;
  onSeek: (time: number) => void;
  onSeekingChange?: (seeking: boolean) => void;
  isLive?: boolean;
}

export default function PlayerTimeline({
  currentTime,
  duration,
  bufferedTime = 0,
  onSeek,
  onSeekingChange,
  isLive = false,
}: PlayerTimelineProps) {
  const progressBarRef = useRef<HTMLDivElement>(null);
  const [isHovering, setIsHovering] = useState(false);
  const [hoverPosition, setHoverPosition] = useState(0); // 0 to 1
  const [isDragging, setIsDragging] = useState(false);
  const [dragRatio, setDragRatio] = useState<number | null>(null);
  const lastSeekThrottleRef = useRef<number>(0);

  const playedRatio = duration > 0 ? Math.min(1, Math.max(0, currentTime / duration)) : 0;
  const bufferedRatio = duration > 0 ? Math.min(1, Math.max(0, bufferedTime / duration)) : 0;
  const currentRatio = dragRatio !== null ? dragRatio : playedRatio;

  const calculateRatioFromEvent = useCallback((clientX: number) => {
    if (!progressBarRef.current) return 0;
    const rect = progressBarRef.current.getBoundingClientRect();
    if (rect.width <= 0) return 0;
    const pos = (clientX - rect.left) / rect.width;
    return Math.min(1, Math.max(0, pos));
  }, []);

  const throttledSeek = useCallback((targetTime: number) => {
    const now = performance.now();
    // 100ms throttle keeps playback smooth while scrubbing without stalling decoder
    if (now - lastSeekThrottleRef.current > 100) {
      lastSeekThrottleRef.current = now;
      onSeek(targetTime);
    }
  }, [onSeek]);

  // Pointer Down: Start dragging, capture pointer to this element
  const handlePointerDown = (e: React.PointerEvent<HTMLDivElement>) => {
    if (isLive || duration <= 0) return;
    try {
      e.currentTarget.setPointerCapture(e.pointerId);
    } catch {
      // Safe fallback if pointer capture is not supported
    }

    setIsDragging(true);
    onSeekingChange?.(true);
    const ratio = calculateRatioFromEvent(e.clientX);
    setDragRatio(ratio);
    onSeek(ratio * duration);
  };

  // Pointer Move: Update hover position and scrub if dragging
  const handlePointerMove = (e: React.PointerEvent<HTMLDivElement>) => {
    if (isLive || duration <= 0) return;
    const ratio = calculateRatioFromEvent(e.clientX);
    setHoverPosition(ratio);

    if (isDragging) {
      setDragRatio(ratio);
      throttledSeek(ratio * duration);
    }
  };

  // Pointer Up: Release capture and commit final seek
  const handlePointerUp = (e: React.PointerEvent<HTMLDivElement>) => {
    if (!isDragging) return;
    try {
      if (e.currentTarget.hasPointerCapture(e.pointerId)) {
        e.currentTarget.releasePointerCapture(e.pointerId);
      }
    } catch {
      // Safe fallback
    }

    setIsDragging(false);
    onSeekingChange?.(false);
    const ratio = calculateRatioFromEvent(e.clientX);
    setDragRatio(null);
    onSeek(ratio * duration);
  };

  // Handle pointer cancellation or lost capture
  const handlePointerCancel = (e: React.PointerEvent<HTMLDivElement>) => {
    try {
      if (e.currentTarget.hasPointerCapture(e.pointerId)) {
        e.currentTarget.releasePointerCapture(e.pointerId);
      }
    } catch {
      // Safe fallback
    }
    setIsDragging(false);
    setDragRatio(null);
    onSeekingChange?.(false);
  };

  // Global safety release listeners to guarantee isDragging NEVER gets stuck
  useEffect(() => {
    if (!isDragging) return;

    const handleSafetyRelease = () => {
      setIsDragging(false);
      setDragRatio(null);
      onSeekingChange?.(false);
    };

    window.addEventListener('pointerup', handleSafetyRelease);
    window.addEventListener('pointercancel', handleSafetyRelease);
    window.addEventListener('mouseup', handleSafetyRelease);
    window.addEventListener('touchend', handleSafetyRelease);

    return () => {
      window.removeEventListener('pointerup', handleSafetyRelease);
      window.removeEventListener('pointercancel', handleSafetyRelease);
      window.removeEventListener('mouseup', handleSafetyRelease);
      window.removeEventListener('touchend', handleSafetyRelease);
    };
  }, [isDragging, onSeekingChange]);

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
      onPointerUp={handlePointerUp}
      onPointerCancel={handlePointerCancel}
      onLostPointerCapture={handlePointerCancel}
      onMouseEnter={() => setIsHovering(true)}
      onMouseLeave={() => setIsHovering(false)}
      className="relative w-full py-3 select-none cursor-pointer group flex items-center touch-none"
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
          style={{ width: `${currentRatio * 100}%` }}
        />
      </div>

      {/* Scrub Thumb Handle */}
      <div
        className={`absolute top-1/2 -translate-y-1/2 -translate-x-1/2 w-4 h-4 rounded-full bg-white border-2 border-[#E5B869] shadow-[0_0_12px_#E5B869] pointer-events-none transition-transform duration-150 ${
          isHovering || isDragging ? 'scale-100 opacity-100' : 'scale-0 opacity-0 group-hover:scale-100 group-hover:opacity-100'
        }`}
        style={{ left: `${currentRatio * 100}%` }}
      />
    </div>
  );
}

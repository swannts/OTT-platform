'use client';

import React, { useRef, useState, useEffect, useCallback, useMemo } from 'react';
import '@vidstack/react/player/styles/base.css';
import {
  MediaPlayer,
  MediaProvider,
  Poster,
  useMediaState,
  useMediaRemote,
  useVideoQualityOptions,
  type MediaPlayerInstance,
} from '@vidstack/react';
import { RotateCcw, X } from 'lucide-react';
import TopBar from './TopBar';
import CenterHUD, { HudActionType } from './CenterHUD';
import PlayerControls from './PlayerControls';
import { formatTime } from '../utils/formatTime';
import {
  savePlaybackProgress,
  clearPlaybackProgress,
  getSavedProgress,
} from '../utils/playbackProgress';
import { VideoQuality } from '@/types';
import { extractYouTubeId, isYouTubeVideo } from '@/lib/youtube';

export interface VidstackPlayerProps {
  contentId: string | number;
  contentType: string;
  title: string;
  videoUrl: string;
  urlType?: string;
  qualities?: VideoQuality[];
  initialTime?: number;
  isQualitySwitch?: boolean;
  poster?: string;
  isLive?: boolean;
  onBack?: () => void;
  onQualityChange?: (url: string, qualityType: string) => void;
  onTimeUpdate?: (time: number) => void;
}

// ---------------------------------------------------------------------------
// Internal Cinematic Overlay: Powered by Vidstack Signals & Remote Control
// ---------------------------------------------------------------------------
function VidstackCinematicOverlay({
  contentId,
  contentType,
  title,
  initialTime = 0,
  isQualitySwitch = false,
  isLive = false,
  onBack,
  qualities = [],
  selectedQualityUrl = '',
  onQualityChange,
  onTimeUpdate,
  poster,
}: {
  contentId: string | number;
  contentType: string;
  title: string;
  initialTime?: number;
  isQualitySwitch?: boolean;
  isLive?: boolean;
  onBack?: () => void;
  qualities?: VideoQuality[];
  selectedQualityUrl?: string;
  onQualityChange?: (url: string, qualityType: string) => void;
  onTimeUpdate?: (time: number) => void;
  poster?: string;
}) {
  const remote = useMediaRemote();

  // Vidstack reactive media state
  const paused = useMediaState('paused');
  const isPlaying = !paused;
  const currentTime = useMediaState('currentTime');
  const duration = useMediaState('duration');
  const bufferedEnd = useMediaState('bufferedEnd');
  const volume = useMediaState('volume');
  const isMuted = useMediaState('muted');
  const playbackRate = useMediaState('playbackRate');
  const isFullscreen = useMediaState('fullscreen');
  const isPipSupported = useMediaState('canPictureInPicture');
  const waiting = useMediaState('waiting');
  const canPlay = useMediaState('canPlay');
  const ended = useMediaState('ended');

  // UI state
  const [controlsVisible, setControlsVisible] = useState(true);
  const [isScrubbing, setIsScrubbing] = useState(false);
  const [hudAction, setHudAction] = useState<HudActionType>(null);
  const [hudQualityLabel, setHudQualityLabel] = useState<string>('');

  // Trigger Center HUD animation temporarily
  const triggerHud = useCallback((action: HudActionType) => {
    setHudAction(action);
    const timer = setTimeout(() => {
      setHudAction(null);
    }, 700);
    return () => clearTimeout(timer);
  }, []);


  const hideControlsTimerRef = useRef<NodeJS.Timeout | null>(null);
  const lastSavedTimeRef = useRef<number>(0);
  const [hasAppliedInitialTime, setHasAppliedInitialTime] = useState(false);

  // Resume playback banner state
  const [showResumeBanner, setShowResumeBanner] = useState(false);
  const [resumedFromTime, setResumedFromTime] = useState<number>(0);
  const resumeBannerTimerRef = useRef<NodeJS.Timeout | null>(null);

  // Compute effective initial resume time from initialTime prop or local progress
  const effectiveInitialTime = useMemo(() => {
    if (isLive) return 0;
    return getSavedProgress(contentId, contentType, initialTime);
  }, [contentId, contentType, initialTime, isLive]);

  // Show resume notification toast immediately on mount if saved progress exists from previous session
  useEffect(() => {
    // Only display large resume toast when entering video initially, not mid-playback quality switch, and not for live streams
    if (!isLive && !isQualitySwitch && effectiveInitialTime > 5) {
      setResumedFromTime(effectiveInitialTime);
      setShowResumeBanner(true);
      if (resumeBannerTimerRef.current) clearTimeout(resumeBannerTimerRef.current);
      resumeBannerTimerRef.current = setTimeout(() => {
        setShowResumeBanner(false);
      }, 8000);
    }
  }, [effectiveInitialTime, isQualitySwitch, isLive]);

  // Initial resume seek once duration and canPlay are loaded
  useEffect(() => {
    if (!isLive && !hasAppliedInitialTime && effectiveInitialTime > 0 && canPlay) {
      if (!duration || effectiveInitialTime < duration * 0.95) {
        try {
          remote.seek(effectiveInitialTime);
          if (effectiveInitialTime > 5 && initialTime === 0) {
            setHudQualityLabel(formatTime(effectiveInitialTime));
            triggerHud('resume');
          }
        } catch {}
      }
      setHasAppliedInitialTime(true);
    }
  }, [hasAppliedInitialTime, effectiveInitialTime, canPlay, duration, remote, triggerHud, initialTime, isLive]);

  // Restart playback from beginning handler
  const handleRestartFromBeginning = useCallback(() => {
    try {
      remote.seek(0);
      triggerHud('rewind');
      clearPlaybackProgress(contentId, contentType);
      setShowResumeBanner(false);
      if (resumeBannerTimerRef.current) clearTimeout(resumeBannerTimerRef.current);
    } catch {}
  }, [remote, contentId, contentType]);

  // Playback preservation across stream and quality switches
  const lastKnownTimeRef = useRef<number>(initialTime);
  const wasPlayingBeforeSwitchRef = useRef<boolean>(false);
  const isPendingQualitySwitchRef = useRef<boolean>(false);

  // Keep lastKnownTime updated during normal playback and notify parent
  useEffect(() => {
    if (!isPendingQualitySwitchRef.current && currentTime > 0) {
      lastKnownTimeRef.current = currentTime;
      wasPlayingBeforeSwitchRef.current = isPlaying;
      onTimeUpdate?.(currentTime);
    }
  }, [currentTime, isPlaying, onTimeUpdate]);

  // Seamlessly resume playback at the exact timestamp when a new quality stream loads
  useEffect(() => {
    if (isPendingQualitySwitchRef.current && canPlay && duration > 0) {
      isPendingQualitySwitchRef.current = false;
      const targetTime = lastKnownTimeRef.current;
      if (targetTime > 0 && targetTime < duration) {
        try {
          remote.seek(targetTime);
          if (wasPlayingBeforeSwitchRef.current) {
            const playResult = remote.play() as unknown;
            if (
              playResult &&
              typeof playResult === 'object' &&
              'catch' in playResult &&
              typeof (playResult as { catch: (fn: () => void) => unknown }).catch === 'function'
            ) {
              (playResult as { catch: (fn: () => void) => unknown }).catch(() => {});
            }
          }
        } catch {}
      }
    }
  }, [canPlay, duration, remote]);

  // Periodic Progress Saving while actively playing (every 8s)
  useEffect(() => {
    if (isLive || !contentId || currentTime <= 5 || !isPlaying) return;

    if (Math.abs(currentTime - lastSavedTimeRef.current) >= 8) {
      lastSavedTimeRef.current = currentTime;
      savePlaybackProgress(contentId, contentType, currentTime, duration);
    }
  }, [contentId, contentType, currentTime, duration, isPlaying, isLive]);

  // Immediate save on pause
  useEffect(() => {
    if (!isLive && paused && contentId && currentTime > 5) {
      savePlaybackProgress(contentId, contentType, currentTime, duration);
    }
  }, [paused, contentId, contentType, currentTime, duration, isLive]);

  // Handle video completion
  useEffect(() => {
    if (!isLive && ended && contentId) {
      savePlaybackProgress(contentId, contentType, duration || 0, duration);
    }
  }, [ended, contentId, contentType, duration, isLive]);

  // Persist progress on page unload or component unmount
  useEffect(() => {
    const handleBeforeUnload = () => {
      if (!isLive && contentId && currentTime > 5) {
        savePlaybackProgress(contentId, contentType, currentTime, duration);
      }
    };

    window.addEventListener('beforeunload', handleBeforeUnload);
    return () => {
      window.removeEventListener('beforeunload', handleBeforeUnload);
      if (!isLive && contentId && currentTime > 5) {
        savePlaybackProgress(contentId, contentType, currentTime, duration);
      }
      if (resumeBannerTimerRef.current) {
        clearTimeout(resumeBannerTimerRef.current);
      }
    };
  }, [contentId, contentType, currentTime, duration, isLive]);

  // Vidstack reactive adaptive HLS quality options
  const rawHlsQualities = useVideoQualityOptions({ auto: 'Auto' });
  const hlsQualities = useMemo(() => {
    if (!rawHlsQualities || rawHlsQualities.disabled || rawHlsQualities.length <= 1) {
      return [];
    }
    return rawHlsQualities.map((q) => ({
      label: q.label,
      selected: q.selected,
      select: () => {
        try {
          q.select();
          setHudQualityLabel(q.label);
          triggerHud('quality');
        } catch {}
      },
    }));
  }, [rawHlsQualities]);


  // Controls Auto-Hide
  const resetHideTimer = useCallback(() => {
    setControlsVisible(true);
    if (hideControlsTimerRef.current) {
      clearTimeout(hideControlsTimerRef.current);
    }
    // Only auto-hide when actively playing and NOT dragging the scrubber
    if (isPlaying && !isScrubbing) {
      hideControlsTimerRef.current = setTimeout(() => {
        setControlsVisible(false);
      }, 3000);
    }
  }, [isPlaying, isScrubbing]);

  const handleMouseMove = useCallback(() => {
    resetHideTimer();
  }, [resetHideTimer]);

  const handleMouseLeave = useCallback(() => {
    if (isPlaying && !isScrubbing) {
      setControlsVisible(false);
    }
  }, [isPlaying, isScrubbing]);

  // Prevent controls from hiding while scrubbing
  useEffect(() => {
    if (isScrubbing) {
      setControlsVisible(true);
      if (hideControlsTimerRef.current) {
        clearTimeout(hideControlsTimerRef.current);
      }
    } else if (isPlaying) {
      resetHideTimer();
    }
  }, [isScrubbing, isPlaying, resetHideTimer]);

  // Playback Control Actions
  const togglePlay = useCallback(() => {
    resetHideTimer();
    try {
      remote.togglePaused();
    } catch {}
    triggerHud(paused ? 'play' : 'pause');
  }, [paused, remote, resetHideTimer, triggerHud]);

  const handleSeek = useCallback((time: number) => {
    resetHideTimer();
    try {
      remote.seek(time);
    } catch {}
  }, [remote, resetHideTimer]);

  const handleRewind10 = useCallback(() => {
    const target = Math.max(0, currentTime - 10);
    try {
      remote.seek(target);
    } catch {}
    triggerHud('rewind');
  }, [currentTime, remote, triggerHud]);

  const handleForward10 = useCallback(() => {
    const target = Math.min(duration || Infinity, currentTime + 10);
    try {
      remote.seek(target);
    } catch {}
    triggerHud('forward');
  }, [currentTime, duration, remote, triggerHud]);

  const handleVolumeChange = useCallback((newVol: number) => {
    const clamped = Math.max(0, Math.min(1, newVol));
    try {
      remote.changeVolume(clamped);
      if (clamped === 0 && !isMuted) {
        remote.mute();
      } else if (clamped > 0 && isMuted) {
        remote.unmute();
      }
    } catch {}
    triggerHud('volume');
  }, [isMuted, remote, triggerHud]);

  const handleToggleMute = useCallback(() => {
    try {
      remote.toggleMuted();
    } catch {}
    triggerHud(isMuted ? 'unmute' : 'mute');
  }, [isMuted, remote, triggerHud]);

  const handleRateChange = useCallback((rate: number) => {
    try {
      remote.changePlaybackRate(rate);
    } catch {}
  }, [remote]);

  const handleToggleFullscreen = useCallback(() => {
    try {
      remote.toggleFullscreen();
    } catch {}
  }, [remote]);

  const handleTogglePip = useCallback(() => {
    try {
      remote.togglePictureInPicture();
    } catch {}
  }, [remote]);

  const handleQualityChangeWrapper = useCallback(
    (url: string, qualityType: string) => {
      if (url === selectedQualityUrl) return;

      // 1. Capture exact playback timestamp and state
      if (currentTime > 0) {
        lastKnownTimeRef.current = currentTime;
      }
      wasPlayingBeforeSwitchRef.current = isPlaying;
      isPendingQualitySwitchRef.current = true;

      // 2. Trigger HUD visual feedback
      const matched = qualities.find((q) => q.url === url);
      const raw = matched?.quality || 'Quality';
      const cleanLabel = raw
        .replace(/youtube/gi, 'Cinema HD')
        .replace(/default_quality/gi, '1080p FHD');
      setHudQualityLabel(cleanLabel);
      triggerHud('quality');

      // 3. Dispatch quality change to parent
      if (onQualityChange) {
        onQualityChange(url, qualityType);
      }
    },
    [selectedQualityUrl, currentTime, isPlaying, qualities, triggerHud, onQualityChange]
  );

  // Clean luxury source badge (Never displays raw "YouTube")
  const sourceBadge = isLive ? 'LIVE BROADCAST' : '1080p CINEMA FHD';

  return (
    <div
      onMouseMove={handleMouseMove}
      onMouseLeave={handleMouseLeave}
      className={`absolute inset-0 z-10 flex flex-col justify-between select-none ${
        !controlsVisible && isFullscreen ? 'cursor-none' : 'cursor-default'
      }`}
    >
      {/* 1. Top Bar with back button, movie title, live status, and shortcuts */}
      <div
        className={`relative z-30 transition-opacity duration-300 ${
          controlsVisible ? 'opacity-100 pointer-events-auto' : 'opacity-0 pointer-events-none'
        }`}
      >
        <TopBar
          title={title}
          sourceBadge={sourceBadge}
          isLive={isLive}
          onBack={onBack}
        />
      </div>

      {/* 2. Buffering Loading Spinner */}
      {waiting && (
        <div className="absolute inset-0 z-20 flex flex-col items-center justify-center bg-black/70 backdrop-blur-sm pointer-events-none transition-opacity duration-300">
          {poster && (
            <img
              src={poster}
              alt={title}
              className="absolute inset-0 w-full h-full object-cover opacity-20 filter blur-md"
            />
          )}
          <div className="relative z-30 flex flex-col items-center space-y-4">
            <div className="w-14 h-14 rounded-full border-4 border-[#E5B869] border-t-transparent animate-spin shadow-[0_0_20px_rgba(229,184,105,0.5)]" />
            <p className="text-xs font-bold tracking-widest text-[#E5B869] uppercase animate-pulse drop-shadow">
              Buffering Cinema Stream...
            </p>
          </div>
        </div>
      )}

      {/* 3. Center Screen Action HUD (Play, Pause, Skip, Volume badge, Quality change) */}
      <CenterHUD
        action={hudAction}
        volumeLevel={Math.round(volume * 100)}
        qualityLabel={hudQualityLabel}
      />

      {/* 4. Center Screen Click Area to Toggle Play/Pause (does not overlap TopBar or Controls) */}
      <div
        onClick={togglePlay}
        className="absolute inset-x-0 top-16 bottom-24 z-10 cursor-pointer pointer-events-auto"
        aria-label="Click to play or pause"
      />

      {/* 5. Luxury Floating Resume Playback Toast */}
      {showResumeBanner && resumedFromTime > 0 && (
        <div
          data-testid="resume-banner"
          className="absolute bottom-24 sm:bottom-28 left-4 sm:left-6 z-40 flex items-center space-x-2.5 sm:space-x-3.5 px-3.5 sm:px-4 py-2 sm:py-2.5 rounded-xl bg-black/95 backdrop-blur-2xl border border-[#E5B869]/60 shadow-[0_12px_36px_rgba(0,0,0,0.9)] animate-fade-in-up select-none pointer-events-auto"
        >
          <div className="w-2 h-2 rounded-full bg-[#E5B869] animate-pulse flex-shrink-0" />
          <span className="text-xs text-white font-medium">
            Resumed from{' '}
            <strong className="text-[#E5B869] font-mono font-bold">
              {formatTime(resumedFromTime)}
            </strong>
          </span>
          <button
            type="button"
            onClick={handleRestartFromBeginning}
            className="px-2.5 py-1 rounded-lg bg-[#E5B869] hover:bg-[#ffe4a0] text-black text-xs font-bold transition-all shadow-md hover:scale-105 active:scale-95 cursor-pointer ml-1"
            title="Restart playback from 00:00"
          >
            Start Over
          </button>
          <button
            type="button"
            onClick={() => setShowResumeBanner(false)}
            className="text-gray-400 hover:text-white p-1 transition-colors cursor-pointer"
            aria-label="Dismiss resume banner"
          >
            <X className="w-3.5 h-3.5" />
          </button>
        </div>
      )}

      {/* 6. Bottom Controls Bar (Scrubber, Play, Volume, Time, Speed, Fullscreen) */}
      <div
        className={`relative z-30 transition-opacity duration-300 ${
          controlsVisible ? 'opacity-100 pointer-events-auto' : 'opacity-0 pointer-events-none'
        }`}
      >
        <PlayerControls
          isPlaying={isPlaying}
          onTogglePlay={togglePlay}
          currentTime={currentTime}
          duration={duration}
          bufferedTime={bufferedEnd}
          onSeek={handleSeek}
          onSeekingChange={setIsScrubbing}
          onRewind10={handleRewind10}
          onForward10={handleForward10}
          volume={volume}
          isMuted={isMuted}
          onVolumeChange={handleVolumeChange}
          onToggleMute={handleToggleMute}
          playbackRate={playbackRate}
          onRateChange={handleRateChange}
          qualities={qualities}
          selectedQualityUrl={selectedQualityUrl}
          onQualityChange={handleQualityChangeWrapper}
          hlsQualities={hlsQualities}
          isFullscreen={isFullscreen}
          onToggleFullscreen={handleToggleFullscreen}
          onTogglePip={handleTogglePip}
          isPipSupported={isPipSupported}
          isLive={isLive}
        />
      </div>
    </div>
  );
}

// ---------------------------------------------------------------------------
// Main Vidstack Cinematic Player
// ---------------------------------------------------------------------------
export default function VidstackPlayer({
  contentId,
  contentType,
  title,
  videoUrl,
  urlType = 'HLS',
  qualities = [],
  initialTime = 0,
  isQualitySwitch = false,
  poster,
  isLive = false,
  onBack,
  onQualityChange,
  onTimeUpdate,
}: VidstackPlayerProps) {
  const playerRef = useRef<MediaPlayerInstance>(null);

  const resolvedSrc = useMemo(() => {
    if (!videoUrl) return '';
    if (isYouTubeVideo(videoUrl, urlType)) {
      const ytId = extractYouTubeId(videoUrl);
      const canonicalYtUrl = ytId ? `https://www.youtube.com/watch?v=${ytId}` : videoUrl;
      return { src: canonicalYtUrl, type: 'video/youtube' as const };
    }
    const isHls = urlType?.toLowerCase() === 'hls' || videoUrl.includes('.m3u8');
    if (isHls) {
      return { src: videoUrl, type: 'application/x-mpegurl' as const };
    }
    return videoUrl;
  }, [videoUrl, urlType]);


  return (
    <div
      className="relative aspect-video w-full max-h-[85vh] bg-[#05080c] rounded-2xl overflow-hidden shadow-[0_25px_60px_rgba(0,0,0,0.9)] border border-[#E5B869]/20 select-none group [&_iframe.vds-youtube]:pointer-events-none [&_.vds-blocker]:pointer-events-auto [&_.vds-blocker]:z-1"
      data-testid="custom-video-player"
    >
      <MediaPlayer
        key={videoUrl}
        ref={playerRef}
        title={title}
        src={resolvedSrc}
        poster={poster}
        viewType="video"
        streamType={isLive ? 'live' : 'on-demand'}
        currentTime={initialTime > 0 ? initialTime : undefined}
        logLevel="silent"
        autoPlay
        playsInline
        crossOrigin
        className="w-full h-full relative"
        onError={() => {
          // Gracefully suppress player teardown rejections
        }}
        onAutoPlayFail={() => {
          // Gracefully handle browser policy autoplay restrictions
        }}
      >
        <MediaProvider className="w-full h-full object-contain">
          {poster && (
            <Poster
              className="vds-poster object-cover"
              src={poster}
              alt={title}
            />
          )}
        </MediaProvider>

        {/* Custom Luxury OTT Cinematic Overlay Controls */}
        <VidstackCinematicOverlay
          contentId={contentId}
          contentType={contentType}
          title={title}
          initialTime={initialTime}
          isQualitySwitch={isQualitySwitch}
          isLive={isLive}
          onBack={onBack}
          qualities={qualities}
          selectedQualityUrl={videoUrl}
          onQualityChange={onQualityChange}
          onTimeUpdate={onTimeUpdate}
          poster={poster}
        />
      </MediaPlayer>
    </div>
  );
}

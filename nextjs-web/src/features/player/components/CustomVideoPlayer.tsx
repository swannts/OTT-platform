'use client';

import React, { useState, useEffect, useRef, useCallback } from 'react';
import { isYouTubeVideo, extractYouTubeId } from '@/lib/youtube';
import { api } from '@/lib/api';
import { VideoQuality } from '@/types';
import PlayerControls from './PlayerControls';
import TopBar from './TopBar';
import CenterHUD, { HudActionType } from './CenterHUD';
import { useHlsStream } from '../hooks/useHlsStream';

// Declarations for YouTube IFrame API
declare global {
  interface Window {
    onYouTubeIframeAPIReady?: () => void;
    YT?: {
      Player: new (
        elementId: string | HTMLElement,
        options: {
          videoId?: string;
          playerVars?: Record<string, unknown>;
          events?: {
            onReady?: (event: { target: YTPlayerInstance }) => void;
            onStateChange?: (event: { data: number }) => void;
            onError?: (event: { data: number }) => void;
          };
        }
      ) => YTPlayerInstance;
      PlayerState?: {
        UNSTARTED: number;
        ENDED: number;
        PLAYING: number;
        PAUSED: number;
        BUFFERING: number;
        CUED: number;
      };
    };
  }
}

interface YTPlayerInstance {
  playVideo: () => void;
  pauseVideo: () => void;
  seekTo: (seconds: number, allowSeekAhead?: boolean) => void;
  setVolume: (volume: number) => void;
  getVolume: () => number;
  mute: () => void;
  unMute: () => void;
  isMuted: () => boolean;
  getCurrentTime: () => number;
  getDuration: () => number;
  getPlayerState: () => number;
  setPlaybackRate: (rate: number) => void;
  destroy: () => void;
}

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

const STORAGE_KEY_VOLUME = 'noor_player_volume';
const STORAGE_KEY_MUTED = 'noor_player_muted';

export default function CustomVideoPlayer({
  contentId,
  contentType,
  title,
  videoUrl = '',
  urlType = 'URL',
  qualities = [],
  initialTime = 0,
  poster,
  isLive = false,
  onBack,
}: CustomVideoPlayerProps) {
  // Container & Refs
  const containerRef = useRef<HTMLDivElement>(null);
  const videoRef = useRef<HTMLVideoElement>(null);
  const ytPlayerRef = useRef<YTPlayerInstance | null>(null);
  const hideControlsTimerRef = useRef<NodeJS.Timeout | null>(null);
  const ytPollIntervalRef = useRef<NodeJS.Timeout | null>(null);

  // Stream & Quality Selection
  const [selectedUrl, setSelectedUrl] = useState<string>(videoUrl);
  const [selectedUrlType, setSelectedUrlType] = useState<string>(urlType);

  // Player State
  const [isPlaying, setIsPlaying] = useState(false);
  const [currentTime, setCurrentTime] = useState(0);
  const [duration, setDuration] = useState(0);
  const [bufferedTime, setBufferedTime] = useState(0);
  const [playbackRate, setPlaybackRate] = useState(1.0);
  const [isLoading, setIsLoading] = useState(true);

  // Volume & Mute State
  const [volume, setVolumeState] = useState<number>(() => {
    if (typeof window !== 'undefined') {
      const saved = localStorage.getItem(STORAGE_KEY_VOLUME);
      if (saved) return parseFloat(saved);
    }
    return 0.9;
  });
  const [isMuted, setIsMutedState] = useState<boolean>(() => {
    if (typeof window !== 'undefined') {
      return localStorage.getItem(STORAGE_KEY_MUTED) === 'true';
    }
    return false;
  });

  // UI Visibility & Overlays
  const [controlsVisible, setControlsVisible] = useState(true);
  const [isFullscreen, setIsFullscreen] = useState(false);
  const [hudAction, setHudAction] = useState<HudActionType>(null);
  const [isPipSupported, setIsPipSupported] = useState(false);
  const [hasAppliedInitialTime, setHasAppliedInitialTime] = useState(false);

  const isCurrentYouTube = isYouTubeVideo(selectedUrl, selectedUrlType);
  const ytVideoId = isCurrentYouTube ? extractYouTubeId(selectedUrl) : null;

  // Initialize HLS for native HTML5 video
  useHlsStream(videoRef, {
    src: isCurrentYouTube ? '' : selectedUrl,
    autoPlay: true,
    onReady: () => {
      setIsLoading(false);
    },
    onError: () => {
      setIsLoading(false);
    },
  });

  // Check Picture-in-Picture support
  useEffect(() => {
    if (typeof document !== 'undefined') {
      setIsPipSupported('pictureInPictureEnabled' in document);
    }
  }, []);

  // Update URL state if incoming props change
  useEffect(() => {
    if (videoUrl) {
      setSelectedUrl(videoUrl);
      setSelectedUrlType(urlType);
    }
  }, [videoUrl, urlType]);

  // Trigger Center HUD animation temporarily
  const triggerHud = useCallback((action: HudActionType) => {
    setHudAction(action);
    const timer = setTimeout(() => {
      setHudAction(null);
    }, 700);
    return () => clearTimeout(timer);
  }, []);

  // Controls Auto-Hide
  const resetHideTimer = useCallback(() => {
    setControlsVisible(true);
    if (hideControlsTimerRef.current) {
      clearTimeout(hideControlsTimerRef.current);
    }
    if (isPlaying) {
      hideControlsTimerRef.current = setTimeout(() => {
        setControlsVisible(false);
      }, 2500);
    }
  }, [isPlaying]);

  const handleMouseMove = useCallback(() => {
    resetHideTimer();
  }, [resetHideTimer]);

  const handleMouseLeave = useCallback(() => {
    if (isPlaying) {
      setControlsVisible(false);
    }
  }, [isPlaying]);

  // Listen to browser Fullscreen changes
  useEffect(() => {
    const handleFullscreenChange = () => {
      setIsFullscreen(!!document.fullscreenElement);
    };
    document.addEventListener('fullscreenchange', handleFullscreenChange);
    return () => document.removeEventListener('fullscreenchange', handleFullscreenChange);
  }, []);

  // -------------------------------------------------------------
  // YouTube IFrame API Initialization & Polling
  // -------------------------------------------------------------
  useEffect(() => {
    if (!isCurrentYouTube || !ytVideoId) return;

    let destroyed = false;
    const containerId = `noor-yt-player-${contentId}`;

    const initPlayer = () => {
      if (destroyed || !window.YT || !window.YT.Player) return;

      ytPlayerRef.current = new window.YT.Player(containerId, {
        videoId: ytVideoId,
        playerVars: {
          autoplay: 1,
          controls: 0,
          rel: 0,
          modestbranding: 1,
          disablekb: 1,
          fs: 0,
          iv_load_policy: 3,
          start: initialTime > 0 ? Math.floor(initialTime) : 0,
        },
        events: {
          onReady: (e) => {
            if (destroyed) return;
            setIsLoading(false);
            if (isMuted) {
              e.target.mute();
            } else {
              e.target.setVolume(volume * 100);
            }
            e.target.playVideo();
          },
          onStateChange: (e) => {
            if (destroyed) return;
            if (window.YT?.PlayerState) {
              const state = e.data;
              if (state === window.YT.PlayerState.PLAYING) {
                setIsPlaying(true);
                setIsLoading(false);
              } else if (state === window.YT.PlayerState.PAUSED) {
                setIsPlaying(false);
              } else if (state === window.YT.PlayerState.BUFFERING) {
                setIsLoading(true);
              } else if (state === window.YT.PlayerState.ENDED) {
                setIsPlaying(false);
              }
            }
          },
        },
      });
    };

    // Load YouTube API script if not present
    if (!window.YT) {
      const tag = document.createElement('script');
      tag.src = 'https://www.youtube.com/iframe_api';
      const firstScriptTag = document.getElementsByTagName('script')[0];
      firstScriptTag?.parentNode?.insertBefore(tag, firstScriptTag);
      window.onYouTubeIframeAPIReady = initPlayer;
    } else {
      initPlayer();
    }

    // Poll YouTube player progress & duration
    ytPollIntervalRef.current = setInterval(() => {
      if (ytPlayerRef.current && typeof ytPlayerRef.current.getCurrentTime === 'function') {
        const cur = ytPlayerRef.current.getCurrentTime();
        const dur = ytPlayerRef.current.getDuration();
        if (typeof cur === 'number' && !isNaN(cur)) setCurrentTime(cur);
        if (typeof dur === 'number' && !isNaN(dur) && dur > 0) setDuration(dur);
      }
    }, 400);

    return () => {
      destroyed = true;
      if (ytPollIntervalRef.current) clearInterval(ytPollIntervalRef.current);
      if (ytPlayerRef.current) {
        try {
          ytPlayerRef.current.destroy();
        } catch {}
        ytPlayerRef.current = null;
      }
    };
  }, [isCurrentYouTube, ytVideoId, contentId, initialTime, isMuted, volume]);

  // Apply initial resume timestamp for native video
  useEffect(() => {
    if (!hasAppliedInitialTime && initialTime > 0 && videoRef.current && !isCurrentYouTube) {
      const handleMetadata = () => {
        if (videoRef.current && initialTime > 0) {
          videoRef.current.currentTime = initialTime;
          setHasAppliedInitialTime(true);
        }
      };
      const v = videoRef.current;
      v.addEventListener('loadedmetadata', handleMetadata);
      return () => v.removeEventListener('loadedmetadata', handleMetadata);
    }
  }, [initialTime, hasAppliedInitialTime, isCurrentYouTube]);

  // Periodic Continue Watching sync
  useEffect(() => {
    if (!contentId || currentTime <= 5 || !isPlaying) return;

    const interval = setInterval(() => {
      api.saveContinueWatching(
        contentId,
        contentType,
        Math.floor(currentTime),
        Math.floor(duration || 3600)
      ).catch(() => {});
    }, 15000);

    return () => clearInterval(interval);
  }, [contentId, contentType, currentTime, duration, isPlaying]);

  // -------------------------------------------------------------
  // Controls Handlers
  // -------------------------------------------------------------
  const togglePlay = useCallback(() => {
    resetHideTimer();
    if (isCurrentYouTube && ytPlayerRef.current) {
      if (isPlaying) {
        ytPlayerRef.current.pauseVideo();
        setIsPlaying(false);
        triggerHud('pause');
      } else {
        ytPlayerRef.current.playVideo();
        setIsPlaying(true);
        triggerHud('play');
      }
      return;
    }

    if (videoRef.current) {
      if (videoRef.current.paused) {
        videoRef.current.play().catch(() => {});
        setIsPlaying(true);
        triggerHud('play');
      } else {
        videoRef.current.pause();
        setIsPlaying(false);
        triggerHud('pause');
      }
    }
  }, [isPlaying, isCurrentYouTube, resetHideTimer, triggerHud]);

  const seek = useCallback((time: number) => {
    resetHideTimer();
    setCurrentTime(time);
    if (isCurrentYouTube && ytPlayerRef.current) {
      ytPlayerRef.current.seekTo(time, true);
      return;
    }
    if (videoRef.current) {
      videoRef.current.currentTime = time;
    }
  }, [isCurrentYouTube, resetHideTimer]);

  const rewind10 = useCallback(() => {
    const target = Math.max(0, currentTime - 10);
    seek(target);
    triggerHud('rewind');
  }, [currentTime, seek, triggerHud]);

  const forward10 = useCallback(() => {
    const target = Math.min(duration || Infinity, currentTime + 10);
    seek(target);
    triggerHud('forward');
  }, [currentTime, duration, seek, triggerHud]);

  const changeVolume = useCallback((newVol: number) => {
    const clamped = Math.max(0, Math.min(1, newVol));
    setVolumeState(clamped);
    setIsMutedState(clamped === 0);
    localStorage.setItem(STORAGE_KEY_VOLUME, String(clamped));
    localStorage.setItem(STORAGE_KEY_MUTED, String(clamped === 0));

    if (isCurrentYouTube && ytPlayerRef.current) {
      ytPlayerRef.current.setVolume(clamped * 100);
      if (clamped === 0) ytPlayerRef.current.mute();
      else ytPlayerRef.current.unMute();
    } else if (videoRef.current) {
      videoRef.current.volume = clamped;
      videoRef.current.muted = clamped === 0;
    }
    triggerHud('volume');
  }, [isCurrentYouTube, triggerHud]);

  const toggleMute = useCallback(() => {
    const nextMuted = !isMuted;
    setIsMutedState(nextMuted);
    localStorage.setItem(STORAGE_KEY_MUTED, String(nextMuted));

    if (isCurrentYouTube && ytPlayerRef.current) {
      if (nextMuted) ytPlayerRef.current.mute();
      else ytPlayerRef.current.unMute();
    } else if (videoRef.current) {
      videoRef.current.muted = nextMuted;
    }
    triggerHud(nextMuted ? 'mute' : 'unmute');
  }, [isMuted, isCurrentYouTube, triggerHud]);

  const changePlaybackRate = useCallback((rate: number) => {
    setPlaybackRate(rate);
    if (isCurrentYouTube && ytPlayerRef.current) {
      ytPlayerRef.current.setPlaybackRate(rate);
    } else if (videoRef.current) {
      videoRef.current.playbackRate = rate;
    }
  }, [isCurrentYouTube]);

  const toggleFullscreen = useCallback(async () => {
    if (!containerRef.current) return;
    try {
      if (!document.fullscreenElement) {
        await containerRef.current.requestFullscreen();
      } else {
        await document.exitFullscreen();
      }
    } catch {}
  }, []);

  const togglePip = useCallback(async () => {
    if (!videoRef.current || !isPipSupported) return;
    try {
      if (document.pictureInPictureElement) {
        await document.exitPictureInPicture();
      } else {
        await videoRef.current.requestPictureInPicture();
      }
    } catch {}
  }, [isPipSupported]);

  const handleQualityChange = useCallback((url: string, qualityType: string) => {
    const resumeTime = currentTime;
    setSelectedUrl(url);
    setSelectedUrlType(qualityType);
    setHasAppliedInitialTime(false);
    setTimeout(() => {
      seek(resumeTime);
    }, 200);
  }, [currentTime, seek]);

  // -------------------------------------------------------------
  // Keyboard Shortcuts Listener
  // -------------------------------------------------------------
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      // Don't intercept if user is typing in an input
      const activeTag = (document.activeElement?.tagName || '').toLowerCase();
      if (activeTag === 'input' || activeTag === 'textarea' || activeTag === 'select') {
        return;
      }

      switch (e.key.toLowerCase()) {
        case ' ':
        case 'k':
          e.preventDefault();
          togglePlay();
          break;
        case 'arrowleft':
        case 'j':
          e.preventDefault();
          rewind10();
          break;
        case 'arrowright':
        case 'l':
          e.preventDefault();
          forward10();
          break;
        case 'arrowup':
          e.preventDefault();
          changeVolume(Math.min(1, volume + 0.1));
          break;
        case 'arrowdown':
          e.preventDefault();
          changeVolume(Math.max(0, volume - 0.1));
          break;
        case 'm':
          e.preventDefault();
          toggleMute();
          break;
        case 'f':
          e.preventDefault();
          toggleFullscreen();
          break;
        case 'p':
          if (isPipSupported) {
            e.preventDefault();
            togglePip();
          }
          break;
        default:
          break;
      }
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [
    togglePlay,
    rewind10,
    forward10,
    changeVolume,
    toggleMute,
    toggleFullscreen,
    togglePip,
    volume,
    isPipSupported,
  ]);

  // Timeupdate for native video
  const handleNativeTimeUpdate = () => {
    if (videoRef.current) {
      setCurrentTime(videoRef.current.currentTime);
      setDuration(videoRef.current.duration || 0);

      // Buffered buffer
      if (videoRef.current.buffered.length > 0) {
        setBufferedTime(videoRef.current.buffered.end(videoRef.current.buffered.length - 1));
      }
    }
  };

  const sourceBadge = isCurrentYouTube ? 'YouTube HD' : isLive ? 'LIVE HD' : '1080p FHD';

  return (
    <div
      ref={containerRef}
      onMouseMove={handleMouseMove}
      onMouseLeave={handleMouseLeave}
      className={`relative aspect-video w-full max-h-[85vh] bg-[#05080c] rounded-2xl overflow-hidden shadow-[0_25px_60px_rgba(0,0,0,0.9)] border border-[#E5B869]/20 select-none group ${
        controlsVisible ? 'cursor-default' : 'cursor-none'
      }`}
      data-testid="custom-video-player"
    >
      {/* 1. Underlying Media Engine (Dual YouTube or HTML5 Video) */}
      {isCurrentYouTube ? (
        <div className="w-full h-full relative pointer-events-none">
          <div id={`noor-yt-player-${contentId}`} className="w-full h-full" />
        </div>
      ) : (
        <video
          ref={videoRef}
          poster={poster}
          playsInline
          autoPlay
          onTimeUpdate={handleNativeTimeUpdate}
          onPlay={() => setIsPlaying(true)}
          onPause={() => setIsPlaying(false)}
          onWaiting={() => setIsLoading(true)}
          onPlaying={() => setIsLoading(false)}
          onLoadedMetadata={() => {
            if (videoRef.current) {
              setDuration(videoRef.current.duration || 0);
              videoRef.current.volume = volume;
              videoRef.current.muted = isMuted;
            }
          }}
          className="w-full h-full object-contain"
        />
      )}

      {/* 2. Poster Preview when loading */}
      {isLoading && (
        <div className="absolute inset-0 z-10 flex flex-col items-center justify-center bg-black/85 backdrop-blur-sm pointer-events-none transition-opacity duration-300">
          {poster && (
            <img
              src={poster}
              alt={title}
              className="absolute inset-0 w-full h-full object-cover opacity-20 filter blur-md"
            />
          )}
          <div className="relative z-20 flex flex-col items-center space-y-4">
            <div className="w-14 h-14 rounded-full border-4 border-[#E5B869] border-t-transparent animate-spin shadow-[0_0_20px_rgba(229,184,105,0.4)]" />
            <p className="text-xs font-bold tracking-widest text-[#E5B869] uppercase animate-pulse">
              Buffering Stream...
            </p>
          </div>
        </div>
      )}

      {/* 3. Center Screen Action HUD (Play, Pause, Skip, Volume badge) */}
      <CenterHUD action={hudAction} volumeLevel={Math.round(volume * 100)} />

      {/* 4. Overlay Click Area to Toggle Play/Pause */}
      <div
        onClick={togglePlay}
        className="absolute inset-0 z-10 cursor-pointer pointer-events-auto"
        aria-label="Click to play or pause"
      />

      {/* 5. Top Bar (Title, Quality Badge, Shortcuts) */}
      <div
        className={`transition-opacity duration-300 ${
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

      {/* 6. Bottom Controls Bar (Scrubber, Play, Volume, Time, Speed, Fullscreen) */}
      <div
        className={`transition-opacity duration-300 ${
          controlsVisible ? 'opacity-100 pointer-events-auto' : 'opacity-0 pointer-events-none'
        }`}
      >
        <PlayerControls
          isPlaying={isPlaying}
          onTogglePlay={togglePlay}
          currentTime={currentTime}
          duration={duration}
          bufferedTime={bufferedTime}
          onSeek={seek}
          onRewind10={rewind10}
          onForward10={forward10}
          volume={volume}
          isMuted={isMuted}
          onVolumeChange={changeVolume}
          onToggleMute={toggleMute}
          playbackRate={playbackRate}
          onRateChange={changePlaybackRate}
          qualities={qualities}
          selectedQualityUrl={selectedUrl}
          onQualityChange={handleQualityChange}
          isFullscreen={isFullscreen}
          onToggleFullscreen={toggleFullscreen}
          onTogglePip={togglePip}
          isPipSupported={isPipSupported}
          isLive={isLive}
        />
      </div>
    </div>
  );
}

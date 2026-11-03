'use client';

import React, { useState, useRef, useEffect, useMemo } from 'react';
import { Settings, Check, Gauge, Layers, Sparkles } from 'lucide-react';
import { VideoQuality } from '@/types';

export interface HlsQualityItem {
  label: string;
  selected: boolean;
  select: () => void;
}

interface QualityMenuProps {
  playbackRate: number;
  onRateChange: (rate: number) => void;
  qualities?: VideoQuality[];
  selectedQualityUrl: string;
  onQualityChange: (url: string, urlType: string) => void;
  hlsQualities?: HlsQualityItem[];
}

const PLAYBACK_RATES = [0.5, 0.75, 1.0, 1.25, 1.5, 2.0];

export default function QualityMenu({
  playbackRate,
  onRateChange,
  qualities = [],
  selectedQualityUrl,
  onQualityChange,
  hlsQualities = [],
}: QualityMenuProps) {
  const [isOpen, setIsOpen] = useState(false);
  const [tab, setTab] = useState<'main' | 'speed' | 'quality'>('main');
  const menuRef = useRef<HTMLDivElement>(null);

  // Close when clicking outside
  useEffect(() => {
    if (!isOpen) return;
    const handleClickOutside = (e: MouseEvent) => {
      if (menuRef.current && !menuRef.current.contains(e.target as Node)) {
        setIsOpen(false);
        setTab('main');
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, [isOpen]);

  const hasHlsQualities = hlsQualities.length > 1;
  const hasMultipleSources = qualities.length > 1;
  const showQualityOption = hasHlsQualities || hasMultipleSources;

  // Find active quality label to display in the main settings row
  const activeQualityLabel = useMemo(() => {
    const activeHls = hlsQualities.find((h) => h.selected);
    if (activeHls) return activeHls.label;

    const matchedSource = qualities.find((q) => q.url === selectedQualityUrl);
    if (matchedSource) {
      const raw = matchedSource.quality || 'HD';
      return raw.replace(/youtube/gi, 'Cinema HD').replace(/default_quality/gi, '1080p FHD');
    }

    return 'Auto';
  }, [hlsQualities, qualities, selectedQualityUrl]);

  return (
    <div className="relative inline-block text-left" ref={menuRef}>
      <button
        type="button"
        onClick={() => {
          setIsOpen(!isOpen);
          setTab('main');
        }}
        className={`p-2 rounded-xl transition-colors cursor-pointer ${
          isOpen
            ? 'bg-[#E5B869] text-black shadow-lg shadow-[#E5B869]/30'
            : 'text-gray-300 hover:text-white hover:bg-white/10'
        }`}
        title="Settings (Speed & Quality)"
        aria-label="Settings"
        aria-expanded={isOpen}
      >
        <Settings className="w-5 h-5 transition-transform duration-300 group-hover:rotate-45" />
      </button>

      {isOpen && (
        <div className="absolute right-0 bottom-full mb-3 w-64 rounded-2xl bg-[#0b1017]/95 backdrop-blur-2xl border border-[#E5B869]/30 shadow-2xl p-2 z-50 animate-in fade-in zoom-in-95 duration-150">
          {tab === 'main' && (
            <div className="space-y-1">
              <div className="px-3 py-1.5 text-[11px] font-bold text-gray-400 uppercase tracking-wider border-b border-white/10">
                Playback Settings
              </div>

              {/* Speed Option */}
              <button
                type="button"
                onClick={() => setTab('speed')}
                className="w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs font-semibold text-gray-200 hover:text-white hover:bg-white/10 transition-colors cursor-pointer"
              >
                <div className="flex items-center space-x-2">
                  <Gauge className="w-4 h-4 text-[#E5B869]" />
                  <span>Playback Speed</span>
                </div>
                <span className="text-[#E5B869] font-bold">
                  {playbackRate === 1.0 ? 'Normal' : `${playbackRate}x`} ›
                </span>
              </button>

              {/* Quality Option */}
              {showQualityOption && (
                <button
                  type="button"
                  onClick={() => setTab('quality')}
                  className="w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs font-semibold text-gray-200 hover:text-white hover:bg-white/10 transition-colors cursor-pointer"
                >
                  <div className="flex items-center space-x-2">
                    <Layers className="w-4 h-4 text-[#E5B869]" />
                    <span>Video Quality</span>
                  </div>
                  <span className="text-[#E5B869] font-bold truncate max-w-[90px] text-right">
                    {activeQualityLabel} ›
                  </span>
                </button>
              )}
            </div>
          )}

          {/* Speed Submenu */}
          {tab === 'speed' && (
            <div className="space-y-1">
              <button
                type="button"
                onClick={() => setTab('main')}
                className="w-full text-left px-3 py-1.5 text-[11px] font-bold text-[#E5B869] hover:underline flex items-center space-x-1 cursor-pointer border-b border-white/10"
              >
                <span>‹ Back to Settings</span>
              </button>

              <div className="py-1">
                {PLAYBACK_RATES.map((rate) => (
                  <button
                    key={rate}
                    type="button"
                    onClick={() => {
                      onRateChange(rate);
                      setIsOpen(false);
                      setTab('main');
                    }}
                    className={`w-full flex items-center justify-between px-3 py-1.5 rounded-xl text-xs font-medium transition-colors cursor-pointer ${
                      playbackRate === rate
                        ? 'bg-[#E5B869]/20 text-[#E5B869] font-bold'
                        : 'text-gray-300 hover:text-white hover:bg-white/10'
                    }`}
                  >
                    <span>{rate === 1.0 ? '1.0x (Normal)' : `${rate}x`}</span>
                    {playbackRate === rate && <Check className="w-3.5 h-3.5" />}
                  </button>
                ))}
              </div>
            </div>
          )}

          {/* Quality Submenu */}
          {tab === 'quality' && (
            <div className="space-y-2">
              <button
                type="button"
                onClick={() => setTab('main')}
                className="w-full text-left px-3 py-1.5 text-[11px] font-bold text-[#E5B869] hover:underline flex items-center space-x-1 cursor-pointer border-b border-white/10"
              >
                <span>‹ Back to Settings</span>
              </button>

              <div className="max-h-60 overflow-y-auto space-y-2">
                {/* 1. Adaptive HLS Resolutions */}
                {hasHlsQualities && (
                  <div>
                    <p className="px-3 py-1 text-[10px] font-bold text-[#E5B869] uppercase tracking-wider flex items-center space-x-1">
                      <Sparkles className="w-3 h-3" />
                      <span>Adaptive Resolutions</span>
                    </p>
                    <div className="py-0.5 space-y-0.5">
                      {hlsQualities.map((hls, idx) => (
                        <button
                          key={`hls-${idx}`}
                          type="button"
                          onClick={() => {
                            hls.select();
                            setIsOpen(false);
                            setTab('main');
                          }}
                          className={`w-full flex items-center justify-between px-3 py-1.5 rounded-xl text-xs font-medium transition-colors cursor-pointer ${
                            hls.selected
                              ? 'bg-[#E5B869]/20 text-[#E5B869] font-bold'
                              : 'text-gray-300 hover:text-white hover:bg-white/10'
                          }`}
                        >
                          <span>{hls.label}</span>
                          {hls.selected && <Check className="w-3.5 h-3.5 flex-shrink-0 ml-2" />}
                        </button>
                      ))}
                    </div>
                  </div>
                )}

                {/* 2. Stream Sources & Renditions */}
                {hasMultipleSources && (
                  <div>
                    {hasHlsQualities && (
                      <div className="border-t border-white/10 my-1 pt-1" />
                    )}
                    <p className="px-3 py-1 text-[10px] font-bold text-gray-400 uppercase tracking-wider">
                      Stream Sources
                    </p>
                    <div className="py-0.5 space-y-0.5">
                      {qualities.map((q, idx) => {
                        const isSelected = selectedQualityUrl === q.url;
                        const rawLabel = q.quality || `Source ${idx + 1}`;
                        const label = rawLabel
                          .replace(/youtube/gi, 'Cinema HD')
                          .replace(/default_quality/gi, '1080p FHD');

                        return (
                          <button
                            key={`src-${idx}`}
                            type="button"
                            onClick={() => {
                              if (!isSelected) {
                                onQualityChange(q.url, q.url_type || '');
                              }
                              setIsOpen(false);
                              setTab('main');
                            }}
                            className={`w-full flex items-center justify-between px-3 py-1.5 rounded-xl text-xs font-medium transition-colors cursor-pointer ${
                              isSelected
                                ? 'bg-[#E5B869]/20 text-[#E5B869] font-bold'
                                : 'text-gray-300 hover:text-white hover:bg-white/10'
                            }`}
                          >
                            <span className="truncate">{label}</span>
                            {isSelected && <Check className="w-3.5 h-3.5 flex-shrink-0 ml-2" />}
                          </button>
                        );
                      })}
                    </div>
                  </div>
                )}
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  );
}

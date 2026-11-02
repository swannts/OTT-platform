'use client';

import React, { useState, useRef, useEffect } from 'react';
import { Settings, Check, Gauge, Layers } from 'lucide-react';
import { VideoQuality } from '@/types';

interface QualityMenuProps {
  playbackRate: number;
  onRateChange: (rate: number) => void;
  qualities?: VideoQuality[];
  selectedQualityUrl: string;
  onQualityChange: (url: string, urlType: string) => void;
}

const PLAYBACK_RATES = [0.5, 0.75, 1.0, 1.25, 1.5, 2.0];

export default function QualityMenu({
  playbackRate,
  onRateChange,
  qualities = [],
  selectedQualityUrl,
  onQualityChange,
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

  const hasMultipleQualities = qualities.length > 1;

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
        <div className="absolute right-0 bottom-full mb-3 w-56 rounded-2xl bg-[#0b1017]/95 backdrop-blur-xl border border-[#E5B869]/30 shadow-2xl p-2 z-50 animate-in fade-in zoom-in-95 duration-150">
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
              {hasMultipleQualities && (
                <button
                  type="button"
                  onClick={() => setTab('quality')}
                  className="w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs font-semibold text-gray-200 hover:text-white hover:bg-white/10 transition-colors cursor-pointer"
                >
                  <div className="flex items-center space-x-2">
                    <Layers className="w-4 h-4 text-[#E5B869]" />
                    <span>Quality / Source</span>
                  </div>
                  <span className="text-[#E5B869] font-bold">Auto ›</span>
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
            <div className="space-y-1">
              <button
                type="button"
                onClick={() => setTab('main')}
                className="w-full text-left px-3 py-1.5 text-[11px] font-bold text-[#E5B869] hover:underline flex items-center space-x-1 cursor-pointer border-b border-white/10"
              >
                <span>‹ Back to Settings</span>
              </button>

              <div className="py-1 max-h-48 overflow-y-auto">
                {qualities.map((q, idx) => {
                  const isSelected = selectedQualityUrl === q.url;
                  const label = q.quality || `Source ${idx + 1}`;
                  return (
                    <button
                      key={idx}
                      type="button"
                      onClick={() => {
                        onQualityChange(q.url, q.url_type || '');
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
      )}
    </div>
  );
}


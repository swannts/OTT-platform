'use client';

import React, { useState } from 'react';
import { ArrowLeft, Radio, HelpCircle, X } from 'lucide-react';

interface TopBarProps {
  title: string;
  sourceBadge?: string;
  isLive?: boolean;
  onBack?: () => void;
}

export default function TopBar({
  title,
  sourceBadge = 'HD',
  isLive = false,
  onBack,
}: TopBarProps) {
  const [showShortcuts, setShowShortcuts] = useState(false);

  return (
    <div className="absolute top-0 left-0 right-0 z-20 px-4 sm:px-6 py-4 bg-gradient-to-b from-black/90 via-black/40 to-transparent flex items-center justify-between pointer-events-auto transition-opacity duration-300">
      <div className="flex items-center space-x-3 min-w-0 pr-4">
        {onBack && (
          <button
            onClick={onBack}
            className="p-2 rounded-xl bg-black/40 hover:bg-[#E5B869] text-gray-300 hover:text-black border border-white/10 transition-colors cursor-pointer flex-shrink-0"
            title="Go Back"
            aria-label="Back"
          >
            <ArrowLeft className="w-4 h-4" />
          </button>
        )}

        <div className="flex items-center space-x-2.5 min-w-0">
          {isLive ? (
            <span className="inline-flex items-center space-x-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-extrabold bg-red-600/90 text-white shadow-md uppercase tracking-wider flex-shrink-0 animate-pulse">
              <Radio className="w-3 h-3 animate-spin" />
              <span>LIVE</span>
            </span>
          ) : (
            <span className="w-2 h-2 rounded-full bg-[#E5B869] shadow-[0_0_8px_#E5B869] flex-shrink-0" />
          )}

          <h2 className="text-white font-black text-sm sm:text-base lg:text-lg tracking-tight drop-shadow truncate max-w-sm sm:max-w-md md:max-w-xl">
            {title}
          </h2>
        </div>
      </div>

      <div className="flex items-center space-x-2 flex-shrink-0">
        {/* Source Resolution Badge */}
        <span className="px-2.5 py-1 rounded-lg text-[10px] sm:text-xs font-bold tracking-wider uppercase bg-white/10 hover:bg-white/15 text-[#E5B869] border border-[#E5B869]/30 backdrop-blur-md shadow-sm">
          {sourceBadge}
        </span>

        {/* Keyboard Shortcuts Help Button */}
        <button
          onClick={() => setShowShortcuts(!showShortcuts)}
          className="p-2 rounded-lg bg-black/40 hover:bg-white/10 text-gray-300 hover:text-white border border-white/10 transition-colors cursor-pointer"
          title="Keyboard Shortcuts"
          aria-label="Keyboard Shortcuts"
        >
          <HelpCircle className="w-4 h-4" />
        </button>
      </div>

      {/* Shortcuts Modal Popover */}
      {showShortcuts && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md">
          <div className="bg-[#0b1017] border border-[#E5B869]/30 rounded-2xl max-w-sm w-full p-5 space-y-4 shadow-2xl relative">
            <div className="flex items-center justify-between pb-2 border-b border-white/10">
              <h3 className="text-sm font-bold text-white flex items-center space-x-2">
                <span className="text-[#E5B869]">⌨</span>
                <span>Keyboard Shortcuts</span>
              </h3>
              <button
                onClick={() => setShowShortcuts(false)}
                className="p-1 rounded-md text-gray-400 hover:text-white transition-colors cursor-pointer"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <div className="space-y-2 text-xs">
              <div className="flex justify-between items-center py-1">
                <span className="text-gray-300">Play / Pause</span>
                <kbd className="px-2 py-0.5 rounded bg-white/10 text-amber-300 font-mono text-[11px]">Space / K</kbd>
              </div>
              <div className="flex justify-between items-center py-1">
                <span className="text-gray-300">Rewind 10 Seconds</span>
                <kbd className="px-2 py-0.5 rounded bg-white/10 text-amber-300 font-mono text-[11px]">← / J</kbd>
              </div>
              <div className="flex justify-between items-center py-1">
                <span className="text-gray-300">Forward 10 Seconds</span>
                <kbd className="px-2 py-0.5 rounded bg-white/10 text-amber-300 font-mono text-[11px]">→ / L</kbd>
              </div>
              <div className="flex justify-between items-center py-1">
                <span className="text-gray-300">Volume Up / Down</span>
                <kbd className="px-2 py-0.5 rounded bg-white/10 text-amber-300 font-mono text-[11px]">↑ / ↓</kbd>
              </div>
              <div className="flex justify-between items-center py-1">
                <span className="text-gray-300">Mute / Unmute</span>
                <kbd className="px-2 py-0.5 rounded bg-white/10 text-amber-300 font-mono text-[11px]">M</kbd>
              </div>
              <div className="flex justify-between items-center py-1">
                <span className="text-gray-300">Toggle Fullscreen</span>
                <kbd className="px-2 py-0.5 rounded bg-white/10 text-amber-300 font-mono text-[11px]">F</kbd>
              </div>
              <div className="flex justify-between items-center py-1">
                <span className="text-gray-300">Picture in Picture</span>
                <kbd className="px-2 py-0.5 rounded bg-white/10 text-amber-300 font-mono text-[11px]">P</kbd>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}


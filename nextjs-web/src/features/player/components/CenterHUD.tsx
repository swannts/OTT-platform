'use client';

import React from 'react';
import { Play, Pause, RotateCcw, RotateCw, Volume2, VolumeX, Layers } from 'lucide-react';

export type HudActionType =
  | 'play'
  | 'pause'
  | 'rewind'
  | 'forward'
  | 'mute'
  | 'unmute'
  | 'volume'
  | 'quality'
  | 'resume'
  | null;

interface CenterHUDProps {
  action: HudActionType;
  volumeLevel?: number;
  qualityLabel?: string;
}

export default function CenterHUD({ action, volumeLevel = 100, qualityLabel = '' }: CenterHUDProps) {
  if (!action) return null;

  return (
    <div
      key={`${action}-${Date.now()}`}
      className="absolute inset-0 flex items-center justify-center pointer-events-none z-30 select-none animate-[fadeOut_0.75s_ease-out_forwards]"
    >
      <div className="min-w-20 min-h-20 sm:min-w-24 sm:min-h-24 px-4 py-3 rounded-full bg-black/85 backdrop-blur-md border border-[#E5B869]/40 text-[#E5B869] flex flex-col items-center justify-center shadow-2xl shadow-black/80 transform scale-100 transition-transform">
        {action === 'play' && <Play className="w-9 h-9 sm:w-10 sm:h-10 fill-current ml-1" />}
        {action === 'pause' && <Pause className="w-9 h-9 sm:w-10 sm:h-10 fill-current" />}
        {action === 'rewind' && (
          <div className="flex flex-col items-center">
            <RotateCcw className="w-8 h-8" />
            <span className="text-[10px] font-extrabold tracking-wider mt-0.5">-10s</span>
          </div>
        )}
        {action === 'forward' && (
          <div className="flex flex-col items-center">
            <RotateCw className="w-8 h-8" />
            <span className="text-[10px] font-extrabold tracking-wider mt-0.5">+10s</span>
          </div>
        )}
        {action === 'mute' && <VolumeX className="w-9 h-9 sm:w-10 sm:h-10 text-red-400" />}
        {action === 'unmute' && <Volume2 className="w-9 h-9 sm:w-10 sm:h-10 text-emerald-400" />}
        {action === 'volume' && (
          <div className="flex flex-col items-center">
            <Volume2 className="w-7 h-7" />
            <span className="text-[11px] font-mono font-bold mt-1">{volumeLevel}%</span>
          </div>
        )}
        {action === 'quality' && (
          <div className="flex flex-col items-center">
            <Layers className="w-7 h-7 text-[#E5B869]" />
            <span className="text-[11px] font-bold tracking-tight mt-1 truncate max-w-[100px]">
              {qualityLabel || 'Quality'}
            </span>
          </div>
        )}
        {action === 'resume' && (
          <div className="flex flex-col items-center">
            <RotateCcw className="w-7 h-7 text-[#E5B869]" />
            <span className="text-[10px] font-extrabold tracking-wider mt-1 text-[#E5B869] uppercase">
              RESUMED
            </span>
            <span className="text-[11px] font-mono font-bold mt-0.5">{qualityLabel}</span>
          </div>
        )}
      </div>
    </div>
  );
}

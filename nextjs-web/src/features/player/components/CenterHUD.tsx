'use client';

import React from 'react';
import { Play, Pause, RotateCcw, RotateCw, Volume2, VolumeX } from 'lucide-react';

export type HudActionType =
  | 'play'
  | 'pause'
  | 'rewind'
  | 'forward'
  | 'mute'
  | 'unmute'
  | 'volume'
  | null;

interface CenterHUDProps {
  action: HudActionType;
  volumeLevel?: number;
}

export default function CenterHUD({ action, volumeLevel = 100 }: CenterHUDProps) {
  if (!action) return null;

  return (
    <div
      key={`${action}-${Date.now()}`}
      className="absolute inset-0 flex items-center justify-center pointer-events-none z-30 select-none animate-[fadeOut_0.75s_ease-out_forwards]"
    >
      <div className="w-20 h-20 sm:w-24 sm:h-24 rounded-full bg-black/75 backdrop-blur-md border border-[#E5B869]/40 text-[#E5B869] flex flex-col items-center justify-center shadow-2xl shadow-black/80 transform scale-100 transition-transform">
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
      </div>
    </div>
  );
}

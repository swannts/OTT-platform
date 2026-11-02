'use client';

import React from 'react';
import Link from 'next/link';
import { Lock, Sparkles, ShieldCheck } from 'lucide-react';

interface LockedPlayerProps {
  title: string;
  poster?: string;
  onSubscribeClick?: () => void;
}

export default function LockedPlayer({ title, poster }: LockedPlayerProps) {
  return (
    <div
      className="relative aspect-video w-full max-h-[85vh] bg-[#05080c] rounded-2xl overflow-hidden flex items-center justify-center border border-[#E5B869]/20 shadow-[0_20px_50px_rgba(0,0,0,0.8)] select-none"
      role="region"
      aria-label="Locked Content Player"
    >
      {/* Background Poster with Cinematic Gradient & Blur */}
      {poster && (
        <div className="absolute inset-0 pointer-events-none">
          <img
            src={poster}
            alt={title}
            className="w-full h-full object-cover opacity-20 filter blur-md scale-105"
          />
          <div className="absolute inset-0 bg-gradient-to-t from-[#05080c] via-[#05080c]/80 to-[#05080c]/60" />
        </div>
      )}

      {/* Golden Geometric Ambient Halo */}
      <div className="absolute w-72 h-72 rounded-full bg-[#E5B869]/10 filter blur-3xl pointer-events-none animate-pulse" />

      {/* Lock Card */}
      <div className="relative z-10 max-w-md mx-4 p-6 sm:p-8 text-center space-y-5 bg-[#0b1017]/85 backdrop-blur-xl border border-[#E5B869]/30 rounded-2xl shadow-2xl">
        <div className="w-16 h-16 rounded-2xl bg-gradient-to-br from-[#E5B869]/20 to-[#E5B869]/5 border border-[#E5B869]/40 text-[#E5B869] flex items-center justify-center mx-auto shadow-inner shadow-[#E5B869]/20">
          <Lock className="w-8 h-8 drop-shadow" />
        </div>

        <div className="space-y-2">
          <span className="inline-flex items-center space-x-1.5 px-3 py-0.5 rounded-full text-[11px] font-bold bg-[#E5B869]/15 text-[#E5B869] border border-[#E5B869]/30 uppercase tracking-wider">
            <Sparkles className="w-3 h-3 fill-current" />
            <span>VIP Exclusive Content</span>
          </span>
          <h3 className="text-xl sm:text-2xl font-black text-white tracking-tight">
            Subscription Required
          </h3>
          <p className="text-xs sm:text-sm text-gray-300 leading-relaxed max-w-sm mx-auto">
            &ldquo;{title}&rdquo; is reserved for NOOR VIP members. Unlock ad-free sacred broadcasts, 4K resolution, and exclusive documentary series.
          </p>
        </div>

        <div className="pt-2 flex flex-col sm:flex-row items-center justify-center gap-3">
          <Link
            href="/pricing"
            className="w-full sm:w-auto flex-1 inline-flex items-center justify-center space-x-2 bg-gradient-to-r from-[#E5B869] to-[#d4a048] hover:from-[#f0c57d] hover:to-[#e5b869] text-black font-extrabold text-sm py-3 px-6 rounded-xl shadow-lg shadow-[#E5B869]/25 hover:shadow-[#E5B869]/40 transition-all duration-200 transform hover:scale-[1.02] active:scale-[0.98] cursor-pointer"
          >
            <Sparkles className="w-4 h-4 fill-black" />
            <span>Upgrade to VIP</span>
          </Link>
          <div className="flex items-center space-x-1 text-xs text-gray-400">
            <ShieldCheck className="w-3.5 h-3.5 text-emerald-400" />
            <span>Cancel anytime</span>
          </div>
        </div>
      </div>
    </div>
  );
}


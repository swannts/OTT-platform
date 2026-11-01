'use client';

import React from 'react';
import Link from 'next/link';
import { Play, Crown, ArrowRight } from 'lucide-react';
import { LiveChannel } from '@/types';

interface LiveNowSectionProps {
  channels?: LiveChannel[];
}

export default function LiveNowSection({ channels = [] }: LiveNowSectionProps) {
  // Use backend channels if available
  const hasBackendChannels = channels && channels.length > 0;

  const featured = hasBackendChannels
    ? {
        id: channels[0].id,
        title: channels[0].name || (channels[0] as any).details?.name || 'Live Broadcast',
        location: (channels[0] as any).details?.category || 'Live Television',
        thumbnail:
          channels[0].poster_image ||
          channels[0].thumbnail_image ||
          (channels[0] as any).poster_tv_image ||
          'https://images.unsplash.com/photo-1591604129939-f1efa4d9f7fa?w=800&auto=format&fit=crop&q=80',
        watchUrl: `/watch/live/${channels[0].id}`,
      }
    : {
        id: 1,
        title: 'Jumma khutba from Masjid Al-Haram',
        location: 'Makkah, Saudi Arabia',
        thumbnail:
          'https://images.unsplash.com/photo-1591604129939-f1efa4d9f7fa?w=800&auto=format&fit=crop&q=80',
        watchUrl: '/live-tv',
      };

  const channelSlice = hasBackendChannels
    ? channels.slice(1, 5).map((ch, idx) => ({
        id: ch.id,
        name: ch.name || (ch as any).details?.name || `Channel ${ch.id}`,
        subtitle: (ch as any).details?.category || 'Live Channel',
        logo:
          ch.poster_image ||
          ch.thumbnail_image ||
          (ch as any).poster_tv_image ||
          '',
        color: ['border-blue-500/30 text-blue-400 bg-blue-900/20', 'border-amber-500/30 text-amber-400 bg-amber-900/20', 'border-emerald-500/30 text-emerald-400 bg-emerald-900/20', 'border-red-500/30 text-red-400 bg-red-900/20'][idx % 4],
      }))
    : [
        { id: 2, name: 'Peace TV', subtitle: 'Live Channel', logo: '', color: 'border-blue-500/30 text-blue-400 bg-blue-900/20' },
        { id: 3, name: 'Huda TV', subtitle: 'Live Channel', logo: '', color: 'border-amber-500/30 text-amber-400 bg-amber-900/20' },
        { id: 4, name: 'iTV', subtitle: 'Live Channel', logo: '', color: 'border-emerald-500/30 text-emerald-400 bg-emerald-900/20' },
        { id: 5, name: 'ATN Bangla', subtitle: 'Live Channel', logo: '', color: 'border-red-500/30 text-red-400 bg-red-900/20' },
      ];

  return (
    <div className="my-8 max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8">
      {/* Section Header with Golden Accent Bar */}
      <div className="flex items-center justify-between mb-4">
        <div className="flex items-center">
          <span className="w-1 h-5 bg-[#E5B869] rounded-full inline-block mr-2.5 flex-shrink-0" />
          <h2 className="text-xl sm:text-2xl font-bold text-white tracking-tight">
            Live Now
          </h2>
        </div>
        <Link
          href="/live-tv"
          className="text-xs sm:text-sm text-[#E5B869] hover:text-[#f3cf8a] font-semibold transition-colors flex items-center space-x-1 group"
        >
          <span>All Channels</span>
          <ArrowRight className="w-3.5 h-3.5 transition-transform group-hover:translate-x-1" />
        </Link>
      </div>

      {/* Row Grid: Featured Live + Channel Cards + Go Premium Card */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-4 sm:gap-5 items-stretch">
        {/* 1. Primary Featured Live Stream */}
        <Link
          href={featured.watchUrl}
          className="group relative lg:col-span-3 rounded-2xl overflow-hidden border border-white/10 hover:border-[#E5B869]/50 shadow-xl transition-all duration-300 block min-h-[160px] sm:min-h-[190px]"
        >
          <img
            src={featured.thumbnail}
            alt={featured.title}
            className="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105"
          />

          {/* Vignette */}
          <div className="absolute inset-0 bg-gradient-to-t from-black via-black/40 to-transparent opacity-90 group-hover:opacity-95 transition-opacity" />

          {/* Live Badge in Top Left */}
          <div className="absolute top-3 left-3 z-10 flex items-center space-x-1.5 bg-red-600 text-white text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full shadow-md">
            <span className="w-1.5 h-1.5 rounded-full bg-white animate-pulse" />
            <span>LIVE</span>
          </div>

          {/* Bottom Title & Subtitle + Play Icon */}
          <div className="absolute bottom-0 left-0 right-0 p-3 sm:p-4 flex items-end justify-between">
            <div className="max-w-[80%]">
              <h3 className="font-bold text-white text-xs sm:text-sm line-clamp-1 group-hover:text-[#E5B869] transition-colors">
                {featured.title}
              </h3>
              <p className="text-[11px] text-gray-300 mt-0.5 truncate">{featured.location}</p>
            </div>

            <div className="w-8 h-8 rounded-full bg-white/20 group-hover:bg-[#E5B869] text-white group-hover:text-black flex items-center justify-center backdrop-blur-md transition-all shadow-lg flex-shrink-0">
              <Play className="w-3.5 h-3.5 fill-current ml-0.5" />
            </div>
          </div>
        </Link>

        {/* 2. Live TV Channels (From Backend) */}
        <div className="lg:col-span-5 grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-3.5">
          {channelSlice.map((ch) => {
            const isPeace = ch.name.toLowerCase().includes('peace');
            const isHuda = ch.name.toLowerCase().includes('huda');
            const isItv = ch.name.toLowerCase().includes('itv');
            const isAtn = ch.name.toLowerCase().includes('atn');

            return (
              <Link
                key={ch.id}
                href={`/live-tv`}
                className="group glass-card rounded-2xl p-3 sm:p-3.5 border border-white/5 hover:border-[#E5B869]/40 flex flex-col items-center justify-center text-center transition-all duration-300 hover:scale-[1.03] min-h-[160px] sm:min-h-[190px]"
              >
                {/* Channel Logo / Icon Container */}
                <div className="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl flex items-center justify-center font-black text-sm mb-3 shadow-inner overflow-hidden flex-shrink-0">
                  {isPeace ? (
                    <div className="w-full h-full bg-[#0d2a4a] border border-blue-400/40 rounded-2xl flex flex-col items-center justify-center p-1 text-center">
                      <svg className="w-7 h-7 text-sky-400" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/>
                      </svg>
                    </div>
                  ) : isHuda ? (
                    <div className="w-full h-full bg-[#2a1d08] border border-amber-500/40 rounded-2xl flex flex-col items-center justify-center p-1 text-center">
                      <div className="w-7 h-7 rounded-full bg-gradient-to-tr from-amber-600 to-yellow-300 flex items-center justify-center shadow-lg shadow-amber-500/30">
                        <span className="w-3.5 h-3.5 rounded-full bg-[#2a1d08]" />
                      </div>
                    </div>
                  ) : isItv ? (
                    <div className="w-full h-full bg-[#0a522a] border border-emerald-400/40 rounded-2xl flex items-center justify-center">
                      <span className="text-white font-black text-xl tracking-tighter lowercase">
                        itv
                      </span>
                    </div>
                  ) : isAtn ? (
                    <div className="w-full h-full bg-[#1e2329] border border-red-500/30 rounded-2xl flex flex-col items-center justify-center p-1">
                      <div className="text-[11px] font-black tracking-tighter bg-gradient-to-r from-red-500 to-emerald-400 bg-clip-text text-transparent uppercase">
                        ATN
                      </div>
                      <span className="text-[9px] text-gray-300 font-semibold">Bangla</span>
                    </div>
                  ) : ch.logo ? (
                    <img
                      src={ch.logo}
                      alt={ch.name}
                      className="w-full h-full object-cover rounded-2xl"
                    />
                  ) : (
                    <div className={`w-full h-full rounded-2xl flex items-center justify-center ${ch.color}`}>
                      <span className="text-xs font-bold leading-tight px-1 uppercase">
                        {ch.name.substring(0, 5)}
                      </span>
                    </div>
                  )}
                </div>

                <h4 className="text-xs sm:text-[13px] font-bold text-white group-hover:text-[#E5B869] transition-colors truncate max-w-full">
                  {ch.name}
                </h4>
                <p className="text-[10.5px] text-gray-400 mt-0.5 truncate max-w-full">Live Channel</p>
              </Link>
            );
          })}
        </div>

        {/* 3. Go Premium Promo Card (Luxury Golden Islamic Card) */}
        <div className="group lg:col-span-4 relative rounded-2xl overflow-hidden border border-[#E5B869]/40 p-5 sm:p-6 flex flex-col justify-between shadow-2xl transition-all duration-300 hover:border-[#E5B869]/70 bg-gradient-to-br from-[#071c18] via-[#051411] to-[#040d0b] min-h-[160px] sm:min-h-[190px]">
          {/* Mosque Minarets Silhouette Overlay positioned to the right */}
          <div
            className="absolute inset-y-0 right-0 w-3/5 opacity-40 pointer-events-none bg-cover bg-center mix-blend-screen"
            style={{
              backgroundImage:
                'url(https://images.unsplash.com/photo-1564769625905-50e93615e769?w=600&auto=format&fit=crop&q=80)',
            }}
          />
          <div className="absolute inset-0 bg-gradient-to-r from-[#071c18] via-[#071c18]/80 to-transparent pointer-events-none" />

          <div className="relative z-10">
            <div className="flex items-center space-x-2 text-[#E5B869] mb-2">
              <Crown className="w-5 h-5 fill-[#E5B869]/30 text-[#E5B869]" />
              <h3 className="font-serif-title text-lg sm:text-xl font-bold tracking-tight text-white">
                Go Premium
              </h3>
            </div>
            <p className="text-xs text-gray-300 leading-relaxed max-w-[220px]">
              Unlock exclusive content, ad-free experience and more.
            </p>
          </div>

          <div className="pt-3 relative z-10">
            <Link
              href="/pricing"
              className="inline-flex items-center space-x-1.5 bg-[#E5B869] hover:bg-[#f3cf8a] text-black font-bold text-xs px-5 py-2.5 rounded-full shadow-lg shadow-[#E5B869]/25 transition-transform active:scale-95"
            >
              <span>Upgrade Now</span>
              <ArrowRight className="w-3.5 h-3.5 ml-0.5" />
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
}

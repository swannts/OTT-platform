'use client';

import React from 'react';
import Link from 'next/link';
import { Play, Crown, ArrowRight, Radio } from 'lucide-react';
import { LiveChannel } from '@/types';
import { useLanguage } from '@/providers';

interface LiveNowSectionProps {
  channels?: LiveChannel[];
}

export default function LiveNowSection({ channels = [] }: LiveNowSectionProps) {
  const { t, locale } = useLanguage();

  if (!channels || channels.length === 0) {
    return null;
  }

  const getChannelTitle = (ch: LiveChannel) => {
    return locale === 'bn'
      ? (ch.name_bn || ch.name || ch.details?.name_bn || ch.details?.name)
      : (ch.name_en || ch.name || ch.details?.name_en || ch.details?.name);
  };

  const featured = {
    id: channels[0].id,
    title: getChannelTitle(channels[0]),
    location: channels[0].details?.category || channels[0].category,
    thumbnail:
      channels[0].poster_image ||
      channels[0].thumbnail_image ||
      channels[0].poster_tv_image,
    watchUrl: '/live-tv',
  };

  const channelSlice = channels.slice(1, 5).map((ch) => ({
    id: ch.id,
    name: getChannelTitle(ch),
    subtitle: ch.details?.category || ch.category,
    logo:
      ch.poster_image ||
      ch.thumbnail_image ||
      ch.poster_tv_image ||
      '',
  }));

  return (
    <div className="w-full">
      {/* Section Header with Golden Accent Bar */}
      <div className="flex items-center justify-between mb-4">
        <div className="flex items-center">
          <span className="w-1 h-5 bg-[#E5B869] rounded-full inline-block mr-2.5 flex-shrink-0" />
          <h2 className="text-xl sm:text-2xl font-bold text-white tracking-tight">
            {t('live_now', 'Live Now')}
          </h2>
        </div>
        <Link
          href="/live-tv"
          className="text-xs sm:text-sm text-[#E5B869] hover:text-[#f3cf8a] font-semibold transition-colors flex items-center space-x-1 group"
        >
          <span>{t('all_channels', 'All Channels')}</span>
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
          {featured.thumbnail ? (
            <img src={featured.thumbnail} alt={featured.title} className="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105" />
          ) : (
            <div className="absolute inset-0 flex items-center justify-center bg-[#10201d]" aria-hidden="true">
              <Radio className="h-12 w-12 text-[#E5B869]/70" />
            </div>
          )}

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
              {featured.location && <p className="text-[11px] text-gray-300 mt-0.5 truncate">{featured.location}</p>}
            </div>

            <div className="w-8 h-8 rounded-full bg-white/20 group-hover:bg-[#E5B869] text-white group-hover:text-black flex items-center justify-center backdrop-blur-md transition-all shadow-lg flex-shrink-0">
              <Play className="w-3.5 h-3.5 fill-current ml-0.5" />
            </div>
          </div>
        </Link>

        {/* 2. Live TV Channels (From Backend) */}
        <div className="lg:col-span-5 grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-3.5">
          {channelSlice.map((ch) => (
              <Link
                key={ch.id}
                href={`/live-tv`}
                className="group glass-card rounded-2xl p-3 sm:p-3.5 border border-white/5 hover:border-[#E5B869]/40 flex flex-col items-center justify-center text-center transition-all duration-300 hover:scale-[1.03] min-h-[160px] sm:min-h-[190px]"
              >
                {/* Channel Logo / Icon Container */}
                <div className="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl flex items-center justify-center font-black text-sm mb-3 shadow-inner overflow-hidden flex-shrink-0">
                  {ch.logo ? (
                    <img
                      src={ch.logo}
                      alt={ch.name}
                      className="w-full h-full object-cover rounded-2xl"
                    />
                  ) : (
                    <div className="w-full h-full rounded-2xl flex items-center justify-center bg-white/5 text-[#E5B869]">
                      <Radio className="h-6 w-6" aria-hidden="true" />
                    </div>
                  )}
                </div>

                <h4 className="text-xs sm:text-[13px] font-bold text-white group-hover:text-[#E5B869] transition-colors truncate max-w-full">
                  {ch.name}
                </h4>
                {ch.subtitle && <p className="text-[10.5px] text-gray-400 mt-0.5 truncate max-w-full">{ch.subtitle}</p>}
              </Link>
          ))}
        </div>

        {/* 3. Go Premium Promo Card (Luxury Golden Islamic Card) */}
        <div className="group lg:col-span-4 relative rounded-2xl overflow-hidden border border-[#E5B869]/40 p-5 sm:p-6 flex flex-col justify-between shadow-2xl transition-all duration-300 hover:border-[#E5B869]/70 bg-gradient-to-br from-[#071c18] via-[#051411] to-[#040d0b] min-h-[160px] sm:min-h-[190px] cursor-pointer">
          <Link href="/pricing" className="absolute inset-0 z-0 cursor-pointer" aria-label="Go Premium" />
          {/* Mosque Minarets Silhouette Overlay positioned to the right */}
          <div
            className="absolute inset-y-0 right-0 w-3/5 opacity-40 pointer-events-none bg-cover bg-center mix-blend-screen"
            style={{
              backgroundImage:
                'url(https://images.unsplash.com/photo-1518684079-3c830dcef090?w=600&auto=format&fit=crop&q=80)',
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

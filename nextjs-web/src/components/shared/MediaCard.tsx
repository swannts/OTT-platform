'use client';

import React from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { MediaItem } from '@/types';
import { useAppDispatch } from '@/store/hooks';
import { setActiveItem } from '@/store/slices/playerSlice';
import { Play } from 'lucide-react';
import { Card, SafeImage, Badge, Heading, Text } from '@/components/ui';
import { DEFAULT_FALLBACK_IMAGE } from '@/constants';
import { useLanguage } from '@/providers';

interface MediaCardProps {
  item: MediaItem;
  rank?: number;
  aspectRatio?: 'poster' | 'backdrop';
  layout?: 'grid' | 'carousel';
}

export default function MediaCard({
  item,
  rank,
  aspectRatio = 'backdrop',
  layout = 'grid',
}: MediaCardProps) {
  const dispatch = useAppDispatch();
  const router = useRouter();
  const { locale } = useLanguage();

  const displayName =
    locale === 'bn'
      ? item.name_bn || item.name || item.title || 'Untitled'
      : item.name_en || item.name || item.title || 'Untitled';

  const rawImage =
    (item.poster_image && item.poster_image.trim()) ||
    (item.poster_url && item.poster_url.trim()) ||
    (item.thumbnail_image && item.thumbnail_image.trim()) ||
    (item.banner_image && item.banner_image.trim()) ||
    DEFAULT_FALLBACK_IMAGE;

  const type = item.type || 'movie';
  const watchUrl = `/watch/${type}/${item.id}`;
  const detailUrl =
    type === 'movie'
      ? `/movies/${item.id}`
      : type === 'tvshow'
      ? `/tv-shows/${item.id}`
      : watchUrl;

  const subtitle =
    item.duration ||
    (type === 'tvshow'
      ? 'Series • 8 Episodes'
      : item.release_year
      ? `${item.release_year}`
      : 'Sacred Feature');

  const isCarousel = layout === 'carousel';

  return (
    <div
      className={`group/card relative transition-all duration-300 ease-out hover:-translate-y-2 hover:scale-[1.03] hover:z-30 select-none min-w-0 will-change-transform ${
        isCarousel ? 'flex-none' : 'w-full'
      }`}
    >
      <Card
        variant="media"
        className={`relative min-w-0 overflow-hidden transition-all duration-300 ease-out border border-white/[0.08] group-hover/card:border-[#E5B869]/80 group-hover/card:shadow-[0_20px_35px_-8px_rgba(229,184,105,0.28),0_12px_24px_-6px_rgba(0,0,0,0.85)] group-hover/card:ring-1 group-hover/card:ring-[#E5B869]/30 rounded-xl sm:rounded-2xl ${
          isCarousel
            ? aspectRatio === 'poster'
              ? 'w-[160px] sm:w-[190px] md:w-[220px] aspect-[2/3]'
              : 'w-[240px] sm:w-[280px] md:w-[320px] aspect-[16/10]'
            : aspectRatio === 'poster'
            ? 'w-full aspect-[2/3]'
            : 'w-full aspect-[16/10]'
        }`}
      >
        {/* Primary full-card navigation link (covers card at z-20 so clicks anywhere trigger detail view) */}
        <Link
          href={detailUrl}
          className="absolute inset-0 z-20 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-[#E5B869] focus-visible:ring-offset-2 focus-visible:ring-offset-[#06090e] rounded-xl sm:rounded-2xl"
          aria-label={`View details for ${displayName}`}
        />

        {/* Top 10 Rank Watermark */}
        {rank !== undefined && (
          <div className="pointer-events-none absolute top-2.5 left-2.5 z-25 w-7 h-7 rounded-lg bg-[#E5B869] text-black font-black text-xs flex items-center justify-center shadow-md">
            #{rank}
          </div>
        )}

        {/* Access VIP Badge */}
        {item.access === 'paid' && (
          <div className="pointer-events-none absolute top-2.5 right-2.5 z-25">
            <Badge variant="vip" />
          </div>
        )}
        {item.access === 'pay-per-view' && (
          <div className="pointer-events-none absolute top-2.5 right-2.5 z-25">
            <Badge className="bg-amber-600 text-white font-extrabold">Rent</Badge>
          </div>
        )}

        {/* Standardized SafeImage with BlurHash */}
        <SafeImage
          src={rawImage}
          alt={displayName}
          fallbackSrc={DEFAULT_FALLBACK_IMAGE}
          blurhash={item.blurhash}
          containerClassName="pointer-events-none"
          className="w-full h-full object-cover transition-transform duration-500 ease-out group-hover/card:scale-108 pointer-events-none"
        />

        {/* Bottom Dark Gradient Vignette with Clear Contrast for Legibility */}
        <div className="pointer-events-none absolute inset-0 bg-gradient-to-t from-[#06090e] from-15% via-[#06090e]/80 via-45% to-transparent opacity-95 group-hover/card:opacity-100 transition-opacity duration-300 z-10" />

        {/* Hover Center Play Button with Fade-Up Animation (Only on card hover) */}
        <div className="absolute inset-0 flex items-center justify-center pointer-events-none z-30">
          <button
            type="button"
            onClick={(e) => {
              e.stopPropagation();
              e.preventDefault();
              dispatch(setActiveItem(item));
              router.push(watchUrl);
            }}
            className="pointer-events-auto w-12 h-12 rounded-full bg-gradient-to-tr from-[#D49E3D] via-[#E5B869] to-[#F7DE9B] text-black flex items-center justify-center shadow-2xl shadow-[#E5B869]/40 transform translate-y-4 scale-75 opacity-0 group-hover/card:translate-y-0 group-hover/card:scale-100 group-hover/card:opacity-100 hover:scale-110 active:scale-95 transition-all duration-300 ease-out cursor-pointer hover:ring-4 hover:ring-[#E5B869]/30"
            aria-label={`Play ${displayName}`}
          >
            <Play className="w-5 h-5 fill-black text-black ml-0.5" />
          </button>
        </div>

        {/* Card Metadata with Smooth Elevation Lift */}
        <div className="pointer-events-none absolute bottom-0 left-0 right-0 p-3 sm:p-3.5 text-white z-25 w-full min-w-0 overflow-hidden box-border transform translate-y-1 group-hover/card:translate-y-0 transition-transform duration-300 ease-out">
          <Heading
            as="h3"
            size="xs"
            className="text-white truncate block group-hover/card:text-[#E5B869] transition-colors duration-200 tracking-tight w-full overflow-hidden font-semibold"
            title={displayName}
          >
            {displayName}
          </Heading>

          <div className="flex items-center justify-between mt-1 w-full min-w-0 gap-2">
            <Text variant="caption" className="truncate flex-1 min-w-0 text-gray-300 group-hover/card:text-gray-200 transition-colors">
              {subtitle}
            </Text>
            {(item.imdb_rating || item.rating) && (
              <Badge variant="rating" rating={item.imdb_rating || item.rating} />
            )}
          </div>
        </div>
      </Card>
    </div>
  );
}

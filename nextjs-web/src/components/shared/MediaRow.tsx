'use client';

import React, { useRef } from 'react';
import { MediaItem } from '@/types';
import MediaCard from './MediaCard';
import { ChevronLeft, ChevronRight, ArrowRight } from 'lucide-react';
import Link from 'next/link';
import { Heading, MediaCardSkeleton } from '@/components/ui';
import { useLanguage } from '@/providers';

interface MediaRowProps {
  title: string;
  items: MediaItem[];
  moreHref?: string;
  showRank?: boolean;
  aspectRatio?: 'poster' | 'backdrop';
  isLoading?: boolean;
}

export default function MediaRow({
  title,
  items,
  moreHref,
  showRank = false,
  aspectRatio = 'poster',
  isLoading = false,
}: MediaRowProps) {
  const rowRef = useRef<HTMLDivElement>(null);
  const { t } = useLanguage();

  const handleScroll = (direction: 'left' | 'right') => {
    if (rowRef.current) {
      const { scrollLeft, clientWidth } = rowRef.current;
      const scrollAmount = clientWidth * 0.75;
      rowRef.current.scrollTo({
        left: direction === 'left' ? scrollLeft - scrollAmount : scrollLeft + scrollAmount,
        behavior: 'smooth',
      });
    }
  };

  if (isLoading) {
    return (
      <div className="relative group/row w-full my-4">
        <div className="flex items-center justify-between mb-3.5">
          <div className="flex items-center">
            <span className="w-1 h-5 bg-[#E5B869] rounded-full inline-block mr-2.5 flex-shrink-0" />
            <Heading as="h2" size="xl">
              {title}
            </Heading>
          </div>
        </div>
        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
          {[...Array(6)].map((_, i) => (
            <MediaCardSkeleton key={i} aspectRatio={aspectRatio} />
          ))}
        </div>
      </div>
    );
  }

  if (!items || items.length === 0) return null;

  return (
    <div className="relative group/row w-full">
      {/* Row Header with Golden Accent Bar, View All link, and Quick Navigation Controls */}
      <div className="flex items-center justify-between mb-3.5">
        <div className="flex items-center">
          <span className="w-1 h-5 bg-[#E5B869] rounded-full inline-block mr-2.5 flex-shrink-0" />
          <Heading as="h2" size="xl">
            {title}
          </Heading>
        </div>

        <div className="flex items-center space-x-3">
          {moreHref && (
            <Link
              href={moreHref}
              className="text-xs sm:text-sm text-[#E5B869] hover:text-[#f3cf8a] font-semibold transition-colors flex items-center space-x-1 group/btn cursor-pointer mr-1"
            >
              <span>{t('view_all', 'View All')}</span>
              <ArrowRight className="w-3.5 h-3.5 transition-transform group-hover/btn:translate-x-1" />
            </Link>
          )}

          {/* Clean header scroll buttons (always accessible, never obscuring poster art) */}
          <div className="hidden sm:flex items-center space-x-1.5">
            <button
              onClick={() => handleScroll('left')}
              className="w-7 h-7 rounded-lg bg-white/5 hover:bg-[#E5B869] hover:text-black text-gray-400 hover:border-[#E5B869] border border-white/10 flex items-center justify-center transition-colors cursor-pointer"
              aria-label="Scroll left"
            >
              <ChevronLeft className="w-4 h-4" />
            </button>
            <button
              onClick={() => handleScroll('right')}
              className="w-7 h-7 rounded-lg bg-white/5 hover:bg-[#E5B869] hover:text-black text-gray-400 hover:border-[#E5B869] border border-white/10 flex items-center justify-center transition-colors cursor-pointer"
              aria-label="Scroll right"
            >
              <ChevronRight className="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>

      {/* Row Carousel Wrapper */}
      <div className="relative">
        {/* Left Floating Arrow Button with Pointer-Event Safety */}
        <button
          onClick={() => handleScroll('left')}
          className="absolute -left-4 top-1/2 -translate-y-1/2 z-30 w-10 h-10 rounded-full bg-black/85 hover:bg-[#E5B869] hover:text-black text-white flex items-center justify-center opacity-0 pointer-events-none group-hover/row:opacity-100 group-hover/row:pointer-events-auto transition-all backdrop-blur-md shadow-2xl border border-white/15 cursor-pointer hover:scale-105 active:scale-95"
          aria-label="Scroll left"
        >
          <ChevronLeft className="w-6 h-6" />
        </button>

        {/* Scrollable Container with breathing room for fade-up hover elevation */}
        <div
          ref={rowRef}
          className="flex space-x-4 sm:space-x-5 overflow-x-auto no-scrollbar py-6 -my-4 px-2 -mx-2 scroll-smooth"
        >
          {items.map((item, index) => (
            <MediaCard
              key={`${item.id}-${index}`}
              item={item}
              rank={showRank ? index + 1 : undefined}
              aspectRatio={aspectRatio}
              layout="carousel"
            />
          ))}
        </div>

        {/* Right Floating Arrow Button with Pointer-Event Safety */}
        <button
          onClick={() => handleScroll('right')}
          className="absolute -right-4 top-1/2 -translate-y-1/2 z-30 w-10 h-10 rounded-full bg-black/85 hover:bg-[#E5B869] hover:text-black text-white flex items-center justify-center opacity-0 pointer-events-none group-hover/row:opacity-100 group-hover/row:pointer-events-auto transition-all backdrop-blur-md shadow-2xl border border-white/15 cursor-pointer hover:scale-105 active:scale-95"
          aria-label="Scroll right"
        >
          <ChevronRight className="w-6 h-6" />
        </button>
      </div>
    </div>
  );
}

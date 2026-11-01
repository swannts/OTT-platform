'use client';

import React, { useRef } from 'react';
import { MediaItem } from '@/types';
import MediaCard from './MediaCard';
import { ChevronLeft, ChevronRight, ArrowRight } from 'lucide-react';
import Link from 'next/link';

interface MediaRowProps {
  title: string;
  items: MediaItem[];
  moreHref?: string;
  showRank?: boolean;
  aspectRatio?: 'poster' | 'backdrop';
}

export default function MediaRow({
  title,
  items,
  moreHref,
  showRank = false,
  aspectRatio = 'poster',
}: MediaRowProps) {
  const rowRef = useRef<HTMLDivElement>(null);

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

  if (!items || items.length === 0) return null;

  return (
    <div className="relative group w-full">
      {/* Row Header with Golden Accent Bar and View All link */}
      <div className="flex items-center justify-between mb-3.5">
        <div className="flex items-center">
          <span className="w-1 h-5 bg-[#E5B869] rounded-full inline-block mr-2.5 flex-shrink-0" />
          <h2 className="text-xl sm:text-2xl font-bold text-white tracking-tight">
            {title}
          </h2>
        </div>

        {moreHref && (
          <Link
            href={moreHref}
            className="text-xs sm:text-sm text-[#E5B869] hover:text-[#f3cf8a] font-semibold transition-colors flex items-center space-x-1 group/btn cursor-pointer"
          >
            <span>View All</span>
            <ArrowRight className="w-3.5 h-3.5 transition-transform group-hover/btn:translate-x-1" />
          </Link>
        )}
      </div>

      {/* Row Carousel Wrapper */}
      <div className="relative">
        {/* Left Arrow Button */}
        <button
          onClick={() => handleScroll('left')}
          className="absolute -left-3 top-1/2 -translate-y-1/2 z-30 w-10 h-10 rounded-full bg-black/80 hover:bg-[#E5B869] hover:text-black text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all backdrop-blur-md shadow-xl border border-white/10 cursor-pointer"
          aria-label="Scroll left"
        >
          <ChevronLeft className="w-6 h-6" />
        </button>

        {/* Scrollable Container */}
        <div
          ref={rowRef}
          className="flex space-x-4 sm:space-x-5 overflow-x-auto no-scrollbar py-2 scroll-smooth"
        >
          {items.map((item, index) => (
            <MediaCard
              key={`${item.id}-${index}`}
              item={item}
              rank={showRank ? index + 1 : undefined}
              aspectRatio={aspectRatio}
            />
          ))}
        </div>

        {/* Right Arrow Button */}
        <button
          onClick={() => handleScroll('right')}
          className="absolute -right-3 top-1/2 -translate-y-1/2 z-30 w-10 h-10 rounded-full bg-black/80 hover:bg-[#E5B869] hover:text-black text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all backdrop-blur-md shadow-xl border border-white/10 cursor-pointer"
          aria-label="Scroll right"
        >
          <ChevronRight className="w-6 h-6" />
        </button>
      </div>
    </div>
  );
}

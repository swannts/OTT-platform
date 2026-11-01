'use client';

import React, { useRef } from 'react';
import { MediaItem } from '@/types';
import MediaCard from './MediaCard';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import Link from 'next/link';

interface MediaRowProps {
  title: string;
  items: MediaItem[];
  moreHref?: string;
  showRank?: boolean;
  aspectRatio?: 'poster' | 'backdrop';
}

export default function MediaRow({ title, items, moreHref, showRank = false, aspectRatio = 'poster' }: MediaRowProps) {
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
    <div className="relative my-8 group">
      {/* Row Header */}
      <div className="flex items-center justify-between mb-3 px-4 sm:px-6 lg:px-8">
        <h2 className="text-lg sm:text-xl font-bold text-white tracking-wide flex items-center space-x-2">
          <span>{title}</span>
        </h2>
        {moreHref && (
          <Link
            href={moreHref}
            className="text-xs text-red-500 hover:text-red-400 font-semibold transition-colors flex items-center space-x-1"
          >
            <span>See All</span>
            <ChevronRight className="w-3.5 h-3.5" />
          </Link>
        )}
      </div>

      {/* Row Carousel Wrapper */}
      <div className="relative">
        {/* Left Arrow Button */}
        <button
          onClick={() => handleScroll('left')}
          className="absolute left-0 top-0 bottom-0 z-30 w-12 bg-black/60 hover:bg-black/90 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity backdrop-blur-sm"
          aria-label="Scroll left"
        >
          <ChevronLeft className="w-7 h-7" />
        </button>

        {/* Scrollable Container */}
        <div
          ref={rowRef}
          className="flex space-x-4 overflow-x-auto no-scrollbar py-4 px-4 sm:px-6 lg:px-8 scroll-smooth"
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
          className="absolute right-0 top-0 bottom-0 z-30 w-12 bg-black/60 hover:bg-black/90 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity backdrop-blur-sm"
          aria-label="Scroll right"
        >
          <ChevronRight className="w-7 h-7" />
        </button>
      </div>
    </div>
  );
}


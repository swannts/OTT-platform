'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { MediaItem } from '@/types';
import { useAppDispatch } from '@/store/hooks';
import { setActiveItem } from '@/store/slices/playerSlice';
import { Play, Info, ChevronLeft, ChevronRight, Star } from 'lucide-react';

interface HeroBannerProps {
  items: MediaItem[];
}

export default function HeroBanner({ items }: HeroBannerProps) {
  const dispatch = useAppDispatch();
  const [currentIndex, setCurrentIndex] = useState(0);

  // Fallback slides in case backend is loading
  const fallbackSlides: MediaItem[] = [
    {
      id: 101,
      name: 'The Life of Prophet Muhammad',
      type: 'tvshow',
      description: 'A journey of mercy, wisdom and guidance for all humanity.',
      banner_image:
        'https://images.unsplash.com/photo-1591604129939-f1efa4d9f7fa?w=1920&auto=format&fit=crop&q=85',
      access: 'free',
    },
    {
      id: 102,
      name: 'Stories of the Holy Sanctuary',
      type: 'movie',
      description: 'The profound architectural and spiritual chronicle of the Holy Kaaba and Masjid Al-Haram.',
      banner_image:
        'https://images.unsplash.com/photo-1564769625905-50e93615e769?w=1920&auto=format&fit=crop&q=85',
      access: 'paid',
    },
  ];

  const slides = items && items.length > 0 ? items : fallbackSlides;

  // Auto-advance carousel
  useEffect(() => {
    if (slides.length <= 1) return;
    const timer = setInterval(() => {
      setCurrentIndex((prev) => (prev + 1) % slides.length);
    }, 9000);
    return () => clearInterval(timer);
  }, [slides.length]);

  const current = slides[currentIndex] || slides[0];
  if (!current) return null;

  const type = current.type || 'movie';
  const watchUrl = `/watch/${type}/${current.id}`;
  const detailUrl = type === 'movie' ? `/movies/${current.id}` : `/tv-shows/${current.id}`;
  const badge = current.access === 'paid' ? 'VIP EXCLUSIVE ★' : 'FEATURED RELEASE ★';
  const backdropUrl =
    (current.banner_image && current.banner_image.trim()) ||
    (current.poster_image && current.poster_image.trim()) ||
    (current.thumbnail_image && current.thumbnail_image.trim()) ||
    'https://images.unsplash.com/photo-1591604129939-f1efa4d9f7fa?w=1920&auto=format&fit=crop&q=85';

  const handlePrev = () => {
    setCurrentIndex((prev) => (prev - 1 + slides.length) % slides.length);
  };

  const handleNext = () => {
    setCurrentIndex((prev) => (prev + 1) % slides.length);
  };

  return (
    <div className="relative w-full h-[76vh] sm:h-[82vh] md:h-[88vh] overflow-hidden select-none">
      {/* Background Cinematic Backdrop Image */}
      <div className="absolute inset-0">
        <img
          src={backdropUrl}
          alt={current.name || 'Hero Banner'}
          className="w-full h-full object-cover object-center transform scale-100 transition-all duration-1000 ease-out"
        />

        {/* Sophisticated Vignettes & Gradients fading into #070a0e */}
        <div className="absolute inset-0 bg-gradient-to-t from-[#070a0e] via-[#070a0e]/40 to-transparent" />
        <div className="absolute inset-0 bg-gradient-to-r from-[#070a0e]/95 via-[#070a0e]/60 to-transparent w-full md:w-4/5 lg:w-3/5" />
        <div className="absolute inset-0 bg-radial-at-c from-transparent via-[#070a0e]/30 to-[#070a0e]/80 pointer-events-none" />
      </div>

      {/* Hero Content Area */}
      <div className="relative max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 h-full flex flex-col justify-end pb-16 sm:pb-20 z-10">
        <div className="max-w-2xl space-y-4">
          {/* Gold Outline Badge */}
          <div className="inline-flex items-center space-x-1.5 border border-[#E5B869]/80 bg-black/40 backdrop-blur-md px-3.5 py-1 rounded-full">
            <span className="text-[11px] font-bold tracking-[0.15em] text-[#E5B869] uppercase">
              {current.access === 'paid' ? 'VIP EXCLUSIVE ★' : 'EXCLUSIVE SERIES ★'}
            </span>
          </div>

          {/* Main Title in Serif Typography */}
          <div className="flex flex-wrap items-center gap-3">
            {current.name?.toLowerCase().includes('prophet muhammad') ? (
              <h1 className="font-serif-title text-4xl sm:text-6xl lg:text-7xl font-bold text-white tracking-tight leading-[1.08] drop-shadow-md">
                <span>The Life of</span>
                <span className="block mt-1 flex-wrap items-center gap-3">
                  Prophet Muhammad
                  <span className="inline-block ml-3 font-arabic text-2xl sm:text-3xl text-[#E5B869]/90 font-normal align-middle select-none">
                    ﷺ
                  </span>
                </span>
              </h1>
            ) : (
              <h1 className="font-serif-title text-4xl sm:text-6xl lg:text-7xl font-bold text-white tracking-tight leading-[1.08] drop-shadow-md">
                {current.name || current.title}
              </h1>
            )}
          </div>

          {/* Subtitle / Description */}
          {current.description && (
            <p className="text-sm sm:text-base text-gray-300 font-light leading-relaxed max-w-xl drop-shadow line-clamp-3">
              {current.description}
            </p>
          )}

          {/* Action Buttons */}
          <div className="flex items-center space-x-3.5 pt-3">
            <Link
              href={watchUrl}
              onClick={() => dispatch(setActiveItem(current))}
              className="flex items-center space-x-2 bg-[#E5B869] hover:bg-[#d8a855] text-black font-bold text-sm px-7 py-3 rounded-full shadow-lg shadow-[#E5B869]/25 transition-all transform active:scale-95"
            >
              <Play className="w-4 h-4 fill-black text-black ml-0.5" />
              <span>Watch Now</span>
            </Link>

            <Link
              href={detailUrl}
              className="flex items-center space-x-2 border border-white/40 hover:border-white/80 hover:bg-white/10 text-white font-medium text-sm px-6 py-3 rounded-full backdrop-blur-md transition-all"
            >
              <Info className="w-4 h-4 text-white" />
              <span>More Info</span>
            </Link>
          </div>
        </div>

        {/* Carousel Arrow Controls & Indicator (Matching mockup layout) */}
        {slides.length > 1 && (
          <div className="flex items-center justify-between w-full mt-8">
            {/* Left side: < then 3 round dots */}
            <div className="flex items-center space-x-3">
              <button
                onClick={handlePrev}
                className="text-gray-400 hover:text-white transition-colors p-1"
                aria-label="Previous Slide"
              >
                <ChevronLeft className="w-5 h-5" />
              </button>

              <div className="flex items-center space-x-2">
                {slides.map((_, idx) => (
                  <button
                    key={idx}
                    onClick={() => setCurrentIndex(idx)}
                    className={`transition-all duration-300 rounded-full ${
                      currentIndex === idx
                        ? 'w-2.5 h-2.5 bg-[#E5B869]'
                        : 'w-2 h-2 bg-white/40 hover:bg-white/70'
                    }`}
                    aria-label={`Go to slide ${idx + 1}`}
                  />
                ))}
              </div>
            </div>

            {/* Right side: > chevron */}
            <button
              onClick={handleNext}
              className="text-gray-400 hover:text-white transition-colors p-1"
              aria-label="Next Slide"
            >
              <ChevronRight className="w-6 h-6" />
            </button>
          </div>
        )}
      </div>
    </div>
  );
}

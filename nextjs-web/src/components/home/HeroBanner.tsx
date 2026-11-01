'use client';

import React, { useState, useEffect, useCallback } from 'react';
import Link from 'next/link';
import { MediaItem } from '@/types';
import { useAppDispatch } from '@/store/hooks';
import { setActiveItem } from '@/store/slices/playerSlice';
import { Play, Info, ChevronLeft, ChevronRight, Star, Clock, Calendar } from 'lucide-react';

interface HeroBannerProps {
  items: MediaItem[];
}

export default function HeroBanner({ items }: HeroBannerProps) {
  const dispatch = useAppDispatch();
  const [currentIndex, setCurrentIndex] = useState(0);
  const [isPaused, setIsPaused] = useState(false);

  // Fallback slides in case backend is loading
  const fallbackSlides: MediaItem[] = [
    {
      id: '01940000-0003-7000-8000-000000000001',
      name: 'The Life of Prophet Muhammad',
      type: 'tvshow',
      description: 'A journey of mercy, wisdom and guidance for all humanity.',
      banner_image:
        'https://images.unsplash.com/photo-1591604129939-f1efa4d9f7fa?w=1920&auto=format&fit=crop&q=85',
      access: 'free',
      imdb_rating: 9.9,
      duration: '45:20',
    },
    {
      id: '01940000-0004-7000-8000-000000000006',
      name: 'Stories of the Holy Sanctuary',
      type: 'movie',
      description: 'The profound architectural and spiritual chronicle of the Holy Kaaba and Masjid Al-Haram.',
      banner_image:
        'https://images.unsplash.com/photo-1564769625905-50e93615e769?w=1920&auto=format&fit=crop&q=85',
      access: 'paid',
      imdb_rating: 9.6,
      duration: '1h 50m',
    },
  ];

  const slides = items && items.length > 0 ? items : fallbackSlides;

  const handleNext = useCallback(() => {
    setCurrentIndex((prev) => (prev + 1) % slides.length);
  }, [slides.length]);

  const handlePrev = useCallback(() => {
    setCurrentIndex((prev) => (prev - 1 + slides.length) % slides.length);
  }, [slides.length]);

  // Auto-advance carousel with pause-on-hover
  useEffect(() => {
    if (slides.length <= 1 || isPaused) return;
    const timer = setInterval(() => {
      handleNext();
    }, 8500);
    return () => clearInterval(timer);
  }, [slides.length, isPaused, handleNext]);

  // Keyboard navigation support
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'ArrowLeft') handlePrev();
      if (e.key === 'ArrowRight') handleNext();
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [handlePrev, handleNext]);

  const current = slides[currentIndex] || slides[0];
  if (!current) return null;

  const type = current.type || 'movie';
  const watchUrl = `/watch/${type}/${current.id}`;
  const detailUrl = type === 'movie' ? `/movies/${current.id}` : `/tv-shows/${current.id}`;
  const badge = current.access === 'paid' ? 'VIP EXCLUSIVE ★' : 'EXCLUSIVE SERIES ★';

  return (
    <div
      className="relative w-full h-[78vh] sm:h-[84vh] md:h-[90vh] overflow-hidden select-none"
      onMouseEnter={() => setIsPaused(true)}
      onMouseLeave={() => setIsPaused(false)}
    >
      {/* 1. Layered Cross-Fading Cinematic Backgrounds */}
      {slides.map((slide, idx) => {
        const isCurrent = idx === currentIndex;
        const imgUrl =
          (slide.banner_image && slide.banner_image.trim()) ||
          (slide.poster_image && slide.poster_image.trim()) ||
          (slide.thumbnail_image && slide.thumbnail_image.trim()) ||
          'https://images.unsplash.com/photo-1591604129939-f1efa4d9f7fa?w=1920&auto=format&fit=crop&q=85';

        return (
          <div
            key={`hero-slide-${slide.id ?? 'item'}-${idx}`}
            className={`absolute inset-0 transition-opacity duration-1000 ease-in-out ${
              isCurrent ? 'opacity-100 z-0' : 'opacity-0 -z-10 pointer-events-none'
            }`}
          >
            <img
              src={imgUrl}
              alt={slide.name || 'Hero Backdrop'}
              className={`w-full h-full object-cover object-center transform transition-transform duration-[12000ms] ease-out ${
                isCurrent ? 'scale-105' : 'scale-100'
              }`}
            />
          </div>
        );
      })}

      {/* 2. Sophisticated Vignettes, Gradients & Ambient Lighting */}
      <div className="absolute inset-0 z-10 pointer-events-none">
        {/* Soft radial golden ambient glow behind typography */}
        <div className="absolute -left-20 top-1/4 w-[500px] h-[500px] bg-[#E5B869]/10 rounded-full blur-[120px] pointer-events-none" />

        {/* Cinematic Vignettes fading into #070a0e */}
        <div className="absolute inset-0 bg-gradient-to-t from-[#070a0e] via-[#070a0e]/40 to-transparent" />
        <div className="absolute inset-0 bg-gradient-to-r from-[#070a0e]/95 via-[#070a0e]/65 to-transparent w-full md:w-4/5 lg:w-3/5" />
        <div className="absolute inset-0 bg-gradient-to-b from-[#070a0e]/70 via-transparent to-transparent h-32" />
      </div>

      {/* 3. Hero Content Area with Staggered Keyed Animation */}
      <div className="relative max-w-[1360px] mx-auto px-8 sm:px-12 md:px-16 lg:px-20 xl:px-24 h-full flex flex-col justify-end pb-12 sm:pb-16 z-20">
        <div key={currentIndex} className="max-w-2xl space-y-4 animate-hero-title">
          {/* Gold Outline Badge & Metadata */}
          <div className="flex flex-wrap items-center gap-2.5">
            <div className="inline-flex items-center space-x-1.5 border border-[#E5B869]/80 bg-black/40 backdrop-blur-md px-3.5 py-1 rounded-full shadow-lg shadow-black/40">
              <span className="text-[11px] font-bold tracking-[0.15em] text-[#E5B869] uppercase">
                {badge}
              </span>
            </div>

            {/* IMDb Rating Badge */}
            {(current.imdb_rating || current.rating) && (
              <div className="inline-flex items-center space-x-1 bg-black/50 backdrop-blur-md border border-white/10 px-2.5 py-1 rounded-full text-xs text-white">
                <Star className="w-3 h-3 fill-[#E5B869] text-[#E5B869]" />
                <span className="font-bold text-gray-200">
                  {current.imdb_rating || current.rating}
                </span>
              </div>
            )}

            {/* Duration Badge */}
            {current.duration && (
              <div className="hidden sm:inline-flex items-center space-x-1 bg-black/50 backdrop-blur-md border border-white/10 px-2.5 py-1 rounded-full text-xs text-gray-300">
                <Clock className="w-3 h-3 text-[#E5B869]" />
                <span>{current.duration}</span>
              </div>
            )}
          </div>

          {/* Main Title in Serif Typography with Arabic Ligature */}
          <div className="flex flex-wrap items-center gap-3">
            {current.name?.toLowerCase().includes('prophet muhammad') ? (
              <h1 className="font-serif-title text-4xl sm:text-6xl lg:text-7xl font-bold text-white tracking-tight leading-[1.08] drop-shadow-2xl">
                <span>The Life of</span>
                <span className="block mt-1 flex-wrap items-center gap-3">
                  Prophet Muhammad
                  <span className="inline-block ml-3 font-arabic text-2xl sm:text-3xl text-[#E5B869] font-normal align-middle select-none drop-shadow">
                    ﷺ
                  </span>
                </span>
              </h1>
            ) : (
              <h1 className="font-serif-title text-4xl sm:text-6xl lg:text-7xl font-bold text-white tracking-tight leading-[1.08] drop-shadow-2xl">
                {current.name || current.title}
              </h1>
            )}
          </div>

          {/* Subtitle / Description with Stagger */}
          {current.description && (
            <p className="animate-hero-desc text-sm sm:text-base text-gray-300/90 font-light leading-relaxed max-w-xl drop-shadow line-clamp-3">
              {current.description}
            </p>
          )}

          {/* Action Buttons with Stagger & Hover Glow */}
          <div className="animate-hero-actions flex items-center space-x-3.5 pt-3">
            <Link
              href={watchUrl}
              onClick={() => dispatch(setActiveItem(current))}
              className="group flex items-center space-x-2 bg-[#E5B869] hover:bg-[#f3cf8a] text-black font-bold text-sm px-7 py-3 rounded-full shadow-lg shadow-[#E5B869]/25 hover:shadow-xl hover:shadow-[#E5B869]/40 transition-all transform hover:scale-[1.03] active:scale-95 cursor-pointer"
            >
              <Play className="w-4 h-4 fill-black text-black ml-0.5 transition-transform group-hover:scale-110" />
              <span>Watch Now</span>
            </Link>

            <Link
              href={detailUrl}
              className="flex items-center space-x-2 border border-white/30 hover:border-[#E5B869]/70 hover:bg-white/10 text-white font-medium text-sm px-6 py-3 rounded-full backdrop-blur-md transition-all transform hover:scale-[1.03] active:scale-95 shadow-lg shadow-black/30 cursor-pointer"
            >
              <Info className="w-4 h-4 text-white" />
              <span>More Info</span>
            </Link>
          </div>
        </div>

        {/* 4. Carousel Interactive Arrow Controls & Animated Dots Indicator */}
        {slides.length > 1 && (
          <div className="flex items-center justify-between w-full mt-8 pt-2">
            {/* Left side: < chevron and animated pill indicator dots */}
            <div className="flex items-center space-x-3">
              <button
                onClick={handlePrev}
                className="w-8 h-8 rounded-full bg-black/40 hover:bg-[#E5B869] hover:text-black text-gray-300 border border-white/15 hover:border-[#E5B869] flex items-center justify-center transition-all backdrop-blur-md shadow-md active:scale-90 cursor-pointer"
                aria-label="Previous Slide"
              >
                <ChevronLeft className="w-4 h-4" />
              </button>

              <div className="flex items-center space-x-2">
                {slides.map((_, idx) => {
                  const isCurrent = currentIndex === idx;
                  return (
                    <button
                      key={`hero-dot-${idx}`}
                      onClick={() => setCurrentIndex(idx)}
                      className={`transition-all duration-500 rounded-full cursor-pointer ${
                        isCurrent
                          ? 'w-7 h-2 bg-[#E5B869] shadow-lg shadow-[#E5B869]/40'
                          : 'w-2 h-2 bg-white/30 hover:bg-white/70 hover:scale-125'
                      }`}
                      aria-label={`Go to slide ${idx + 1}`}
                    />
                  );
                })}
              </div>
            </div>

            {/* Right side: > chevron with hover feedback */}
            <button
              onClick={handleNext}
              className="w-8 h-8 rounded-full bg-black/40 hover:bg-[#E5B869] hover:text-black text-gray-300 border border-white/15 hover:border-[#E5B869] flex items-center justify-center transition-all backdrop-blur-md shadow-md active:scale-90 cursor-pointer"
              aria-label="Next Slide"
            >
              <ChevronRight className="w-4 h-4" />
            </button>
          </div>
        )}
      </div>
    </div>
  );
}

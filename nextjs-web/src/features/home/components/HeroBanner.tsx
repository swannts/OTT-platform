'use client';

import React, { useState, useEffect, useCallback, useRef } from 'react';
import Link from 'next/link';
import { MediaItem } from '@/types';
import { useAppDispatch } from '@/store/hooks';
import { setActiveItem } from '@/store/slices/playerSlice';
import { Play, Info, ChevronLeft, ChevronRight, Star, Clock } from 'lucide-react';
import { SafeImage } from '@/components/ui';
import { useLanguage } from '@/providers';

interface HeroBannerProps {
  items: MediaItem[];
}

export default function HeroBanner({ items }: HeroBannerProps) {
  const dispatch = useAppDispatch();
  const { t, locale } = useLanguage();
  const [currentIndex, setCurrentIndex] = useState(0);
  const [fadeState, setFadeState] = useState<'visible' | 'fading'>('visible');
  const [isPaused, setIsPaused] = useState(false);
  const fadeTimeoutRef = useRef<NodeJS.Timeout | null>(null);

  if (!items || items.length === 0) {
    return null;
  }

  const slides = items;

  // Smooth fade transition handler
  const transitionToSlide = useCallback(
    (nextIdx: number) => {
      if (nextIdx === currentIndex) return;
      if (fadeTimeoutRef.current) clearTimeout(fadeTimeoutRef.current);

      // 1. Begin fade-out of current slide content
      setFadeState('fading');

      // 2. Switch slide index and trigger fade-in after fade-out duration
      fadeTimeoutRef.current = setTimeout(() => {
        setCurrentIndex(nextIdx);
        setFadeState('visible');
      }, 350);
    },
    [currentIndex]
  );

  const handleNext = useCallback(() => {
    const next = (currentIndex + 1) % slides.length;
    transitionToSlide(next);
  }, [currentIndex, slides.length, transitionToSlide]);

  const handlePrev = useCallback(() => {
    const prev = (currentIndex - 1 + slides.length) % slides.length;
    transitionToSlide(prev);
  }, [currentIndex, slides.length, transitionToSlide]);

  // Clean up timers on unmount
  useEffect(() => {
    return () => {
      if (fadeTimeoutRef.current) clearTimeout(fadeTimeoutRef.current);
    };
  }, []);

  // Auto-advance carousel with pause-on-hover
  useEffect(() => {
    if (slides.length <= 1 || isPaused) return;
    const timer = setInterval(() => {
      const next = (currentIndex + 1) % slides.length;
      transitionToSlide(next);
    }, 8500);
    return () => clearInterval(timer);
  }, [slides.length, isPaused, currentIndex, transitionToSlide]);

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

  const isBn = locale === 'bn';
  const displayName = isBn
    ? current.name_bn || current.name || current.title || 'Untitled'
    : current.name_en || current.name || current.title || 'Untitled';

  const displayDescription = isBn
    ? current.description_bn || current.description
    : current.description_en || current.description;

  const type = current.type || 'movie';
  const watchUrl = `/watch/${type}/${current.id}`;
  const detailUrl = type === 'movie' ? `/movies/${current.id}` : `/tv-shows/${current.id}`;
  const badge = current.access === 'paid' ? t('hero_vip_badge', 'VIP EXCLUSIVE ★') : t('hero_series_badge', 'EXCLUSIVE SERIES ★');

  return (
    <div
      className="relative w-full h-[78vh] sm:h-[84vh] md:h-[90vh] overflow-hidden select-none bg-[#070a0e]"
      onMouseEnter={() => setIsPaused(true)}
      onMouseLeave={() => setIsPaused(false)}
    >
      {/* 1. Layered Cinematic Cross-Fading Background Images */}
      {slides.map((slide, idx) => {
        const isCurrent = idx === currentIndex;
        const imgUrl =
          (slide.banner_image && slide.banner_image.trim()) ||
          (slide.poster_image && slide.poster_image.trim()) ||
          (slide.thumbnail_image && slide.thumbnail_image.trim()) ||
          'https://images.unsplash.com/photo-1591604129939-f1efa4d9f7fa?w=1920&auto=format&fit=crop&q=85';

        return (
          <div
            key={`hero-slide-bg-${slide.id ?? idx}`}
            className={`absolute inset-0 transition-opacity duration-1000 ease-in-out ${
              isCurrent ? 'opacity-100 z-0' : 'opacity-0 -z-10 pointer-events-none'
            }`}
          >
            <SafeImage
              src={imgUrl}
              alt={slide.name || 'Hero Backdrop'}
              blurhash={slide.blurhash}
              priority={isCurrent}
              showSkeleton={isCurrent}
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

      {/* 3. Hero Content Area with Smooth Fade & Glide Animation */}
      <div className="relative max-w-[1360px] mx-auto px-8 sm:px-12 md:px-16 lg:px-20 xl:px-24 h-full flex flex-col justify-end pb-12 sm:pb-16 z-20">
        <div
          className={`max-w-2xl space-y-4 transition-all ${
            fadeState === 'visible'
              ? 'opacity-100 translate-y-0 duration-500 ease-out'
              : 'opacity-0 translate-y-2 duration-300 ease-in'
          }`}
        >
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
            {!isBn && current.name?.toLowerCase().trim() === 'the life of prophet muhammad' ? (
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
              <h1 className="font-serif-title text-4xl sm:text-6xl lg:text-7xl font-bold text-white tracking-tight leading-[1.12] drop-shadow-2xl">
                {displayName}
                {(displayName.includes('মুহাম্মদ') || displayName.toLowerCase().includes('muhammad')) &&
                  !displayName.includes('ﷺ') &&
                  !displayName.includes('(সাঃ)') && (
                    <span className="inline-block ml-3 font-arabic text-2xl sm:text-3xl text-[#E5B869] font-normal align-middle select-none drop-shadow">
                      ﷺ
                    </span>
                  )}
              </h1>
            )}
          </div>

          {/* Subtitle / Description */}
          {displayDescription && (
            <p className="text-sm sm:text-base text-gray-300/90 font-light leading-relaxed max-w-xl drop-shadow line-clamp-3">
              {displayDescription}
            </p>
          )}

          {/* Action Buttons */}
          <div className="flex items-center space-x-3.5 pt-3">
            <Link
              href={watchUrl}
              onClick={() => dispatch(setActiveItem(current))}
              className="group flex items-center space-x-2 bg-[#E5B869] hover:bg-[#f3cf8a] text-black font-bold text-sm px-7 py-3 rounded-full shadow-lg shadow-[#E5B869]/25 hover:shadow-xl hover:shadow-[#E5B869]/40 transition-all transform hover:scale-[1.03] active:scale-95 cursor-pointer"
            >
              <Play className="w-4 h-4 fill-black text-black ml-0.5 transition-transform group-hover:scale-110" />
              <span>{t('hero_watch_now', 'Watch Now')}</span>
            </Link>

            <Link
              href={detailUrl}
              className="flex items-center space-x-2 border border-white/30 hover:border-[#E5B869]/70 hover:bg-white/10 text-white font-medium text-sm px-6 py-3 rounded-full backdrop-blur-md transition-all transform hover:scale-[1.03] active:scale-95 shadow-lg shadow-black/30 cursor-pointer"
            >
              <Info className="w-4 h-4 text-white" />
              <span>{t('hero_more_info', 'More Info')}</span>
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
                className="w-8 h-8 rounded-full bg-black/40 hover:bg-[#E5B869] hover:text-black text-gray-300 border border-white/15 hover:border-[#E5B869] flex items-center justify-center transition-all backdrop-blur-md shadow-md active:scale-90 cursor-pointer outline-none focus:outline-none"
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
                      onClick={() => transitionToSlide(idx)}
                      className={`transition-all duration-500 rounded-full cursor-pointer outline-none focus:outline-none ${
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
              className="w-8 h-8 rounded-full bg-black/40 hover:bg-[#E5B869] hover:text-black text-gray-300 border border-white/15 hover:border-[#E5B869] flex items-center justify-center transition-all backdrop-blur-md shadow-md active:scale-90 cursor-pointer outline-none focus:outline-none"
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

'use client';

import React, { useEffect, useState, useRef, Suspense } from 'react';
import { usePathname, useSearchParams } from 'next/navigation';

function NavigationProgressBar() {
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const [progress, setProgress] = useState(0);
  const [isVisible, setIsVisible] = useState(false);
  const timerRef = useRef<NodeJS.Timeout | null>(null);

  // Complete progress on route change
  useEffect(() => {
    if (isVisible) {
      setProgress(100);
      const timeout = setTimeout(() => {
        setIsVisible(false);
        setProgress(0);
      }, 250);
      return () => clearTimeout(timeout);
    }
  }, [pathname, searchParams]);

  // Listen for internal link clicks
  useEffect(() => {
    const handleClick = (e: MouseEvent) => {
      const target = (e.target as HTMLElement)?.closest('a');
      if (!target) return;

      const href = target.getAttribute('href');
      const isInternal =
        href &&
        (href.startsWith('/') || href.startsWith(window.location.origin)) &&
        !href.startsWith('/#') &&
        !href.startsWith('mailto:') &&
        !href.startsWith('tel:') &&
        target.getAttribute('target') !== '_blank' &&
        !e.ctrlKey &&
        !e.metaKey &&
        !e.shiftKey &&
        !e.altKey;

      if (!isInternal) return;

      // Check if target is not the exact same URL
      const currentUrl = window.location.pathname + window.location.search;
      const targetUrl = href.startsWith('http')
        ? new URL(href).pathname + new URL(href).search
        : href;

      if (currentUrl === targetUrl) return;

      // Start progress bar
      if (timerRef.current) clearInterval(timerRef.current);
      setIsVisible(true);
      setProgress(15);

      // Trickle progress up to 85%
      timerRef.current = setInterval(() => {
        setProgress((prev) => {
          if (prev >= 85) {
            if (timerRef.current) clearInterval(timerRef.current);
            return prev;
          }
          const diff = Math.max(1, (85 - prev) * 0.15);
          return Math.min(85, prev + diff);
        });
      }, 150);
    };

    document.addEventListener('click', handleClick, { capture: true });

    return () => {
      document.removeEventListener('click', handleClick, { capture: true });
      if (timerRef.current) clearInterval(timerRef.current);
    };
  }, []);

  if (!isVisible && progress === 0) return null;

  return (
    <div
      aria-hidden="true"
      className="fixed top-0 left-0 right-0 z-[9999] pointer-events-none h-[3px] bg-transparent"
    >
      <div
        className="h-full bg-gradient-to-r from-[#B88A38] via-[#E5B869] to-[#F3CF8A] shadow-[0_0_12px_rgba(229,184,105,0.9)] transition-all duration-200 ease-out"
        style={{
          width: `${progress}%`,
          opacity: isVisible ? 1 : 0,
          transition: progress === 100 ? 'width 150ms ease-out, opacity 250ms 100ms ease-out' : 'width 200ms ease-out',
        }}
      />
    </div>
  );
}

export default function NavigationProgress() {
  return (
    <Suspense fallback={null}>
      <NavigationProgressBar />
    </Suspense>
  );
}


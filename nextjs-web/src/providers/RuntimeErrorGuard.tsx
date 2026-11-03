'use client';

import React, { useEffect } from 'react';

/**
 * Checks if a promise rejection or error is a benign media player teardown warning
 * (e.g. Vidstack provider destruction during unmount/navigation or aborted media play requests).
 */
export function isHarmlessTeardownRejection(reason: unknown): boolean {
  if (!reason) return false;
  const msg =
    typeof reason === 'string'
      ? reason
      : (reason as { message?: string })?.message || String(reason);
  const name =
    typeof (reason as { name?: string })?.name === 'string'
      ? (reason as { name: string }).name
      : '';
  const lower = `${msg} ${name}`.toLowerCase();
  return (
    lower.includes('provider destroyed') ||
    lower.includes('interrupted by a call to pause') ||
    lower.includes('interrupted by a new load request') ||
    lower.includes('the play() request was interrupted') ||
    lower.includes('the play() request was canceled') ||
    lower.includes('the fetching process for the media resource was aborted') ||
    lower.includes('aborterror')
  );
}

// Immediate capture-phase registration and addEventListener wrapper
if (typeof window !== 'undefined') {
  try {
    const origAdd = window.addEventListener;
    window.addEventListener = function (
      type: string,
      listener: EventListenerOrEventListenerObject,
      options?: boolean | AddEventListenerOptions
    ) {
      if (type === 'unhandledrejection' && typeof listener === 'function') {
        const wrapped = function (this: unknown, e: PromiseRejectionEvent) {
          if (isHarmlessTeardownRejection(e?.reason)) {
            if (e.preventDefault) e.preventDefault();
            if (e.stopImmediatePropagation) e.stopImmediatePropagation();
            return;
          }
          return (listener as (e: PromiseRejectionEvent) => void).apply(this, [e]);
        };
        return origAdd.call(this, type, wrapped as EventListener, options);
      }
      if (type === 'error' && typeof listener === 'function') {
        const wrapped = function (this: unknown, e: ErrorEvent) {
          if (isHarmlessTeardownRejection(e?.error || e?.message)) {
            if (e.preventDefault) e.preventDefault();
            if (e.stopImmediatePropagation) e.stopImmediatePropagation();
            return;
          }
          return (listener as (e: ErrorEvent) => void).apply(this, [e]);
        };
        return origAdd.call(this, type, wrapped as EventListener, options);
      }
      return origAdd.call(this, type, listener, options);
    };

    origAdd.call(
      window,
      'unhandledrejection',
      ((event: PromiseRejectionEvent) => {
        if (isHarmlessTeardownRejection(event.reason)) {
          event.preventDefault();
          event.stopImmediatePropagation();
        }
      }) as EventListener,
      true
    );

    origAdd.call(
      window,
      'error',
      ((event: ErrorEvent) => {
        if (isHarmlessTeardownRejection(event.error || event.message)) {
          event.preventDefault();
          event.stopImmediatePropagation();
        }
      }) as EventListener,
      true
    );
  } catch {}
}

/**
 * RuntimeErrorGuard acts as a safety shield preventing benign player lifecycle and
 * unmount rejections from triggering Next.js DevTools runtime error modals.
 */
export function RuntimeErrorGuard({ children }: { children: React.ReactNode }) {
  useEffect(() => {
    const handleRejection = (event: PromiseRejectionEvent) => {
      if (isHarmlessTeardownRejection(event.reason)) {
        event.preventDefault();
        event.stopImmediatePropagation();
      }
    };

    const handleError = (event: ErrorEvent) => {
      if (isHarmlessTeardownRejection(event.error || event.message)) {
        event.preventDefault();
        event.stopImmediatePropagation();
      }
    };

    window.addEventListener('unhandledrejection', handleRejection, true);
    window.addEventListener('error', handleError, true);

    return () => {
      window.removeEventListener('unhandledrejection', handleRejection, true);
      window.removeEventListener('error', handleError, true);
    };
  }, []);

  return <>{children}</>;
}

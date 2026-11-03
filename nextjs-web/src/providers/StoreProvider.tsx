'use client';

import React, { useEffect } from 'react';
import { Provider } from 'react-redux';
import { store } from '@/store';

export default function StoreProvider({ children }: { children: React.ReactNode }) {
  useEffect(() => {
    // Intercept benign internal media provider lifecycle rejections (e.g. Vidstack/YouTube teardown)
    // so Next.js development overlay does not display false-positive Runtime Errors.
    const handleUnhandledRejection = (event: PromiseRejectionEvent) => {
      const reason = event?.reason;
      if (
        reason === 'provider destroyed' ||
        (typeof reason === 'string' && reason.includes('provider destroyed')) ||
        (reason instanceof Error && reason.message?.includes('provider destroyed'))
      ) {
        event.preventDefault();
        event.stopImmediatePropagation?.();
      }
    };

    window.addEventListener('unhandledrejection', handleUnhandledRejection);
    return () => {
      window.removeEventListener('unhandledrejection', handleUnhandledRejection);
    };
  }, []);

  return <Provider store={store}>{children}</Provider>;
}
